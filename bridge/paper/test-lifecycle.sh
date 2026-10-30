#!/bin/sh
# Optional offline integration suite, separate from the production build.
set -eu
cd "$(dirname "$0")"
base=$(pwd -P)
deps=${1:?Usage: sh test-lifecycle.sh /absolute/mock-runtime-jars /scratch/qa/unique-fixture-directory}
scratch=${2:?Provide a fresh isolated fixture directory}
case "$deps" in /*) ;; *) echo 'An absolute runtime dependency directory is required.' >&2; exit 2 ;; esac
case "$scratch" in /scratch/qa/*) ;; *) echo 'Use the isolated /scratch/qa mount.' >&2; exit 2 ;; esac
case "$scratch" in *'/../'*|*'/..'|*'/./'*|*'/.'|*'//'*|*'\'*) echo 'A canonical fixture path is required.' >&2; exit 2 ;; esac
test -f target/VibeCraftBridge-0.1.0-dev.jar || { echo 'Run the offline production build first.' >&2; exit 2; }
test ! -e "$scratch" && test ! -e target/integration-classes || { echo 'Use fresh fixture and integration output directories.' >&2; exit 2; }
(cd "$deps"; sha256sum -c "$base/integration-dependencies.sha256")
classpath=target/classes
runtime=target/integration-classes:target/integration-core
while read -r digest name; do
  classpath=$classpath:$deps/$name
  runtime=$runtime:$deps/$name
done < integration-dependencies.sha256
mkdir -p "$scratch" target/integration-classes target/integration-core
find src/integration/java -name '*.java' -type f | LC_ALL=C sort > target/integration-sources.txt
javac -J-Xmx128m -J-XX:+UseSerialGC -J-XX:ActiveProcessorCount=1 --release 25 -proc:none -Xlint:deprecation,unchecked -Werror \
  -cp "$classpath" -d target/integration-classes @target/integration-sources.txt
# Load the final JavaPlugin from its packaged jar, not the system classloader.
find target/classes -name '*.class' -type f ! -name 'VibeCraftBridge*' | while read -r classfile; do
  destination=target/integration-core/${classfile#target/classes/}
  mkdir -p "$(dirname "$destination")"; cp "$classfile" "$destination"
done
keytool -J-Xmx96m -J-XX:+UseSerialGC -genkeypair -alias fixture -dname CN=localhost -ext SAN=dns:localhost \
  -keystore "$scratch/tls.p12" -storepass fixture-only-password -keypass fixture-only-password \
  -keyalg RSA -keysize 2048 -validity 1 -storetype PKCS12 > "$scratch/keytool.log" 2>&1
keytool -J-Xmx96m -J-XX:+UseSerialGC -exportcert -alias fixture -keystore "$scratch/tls.p12" \
  -storepass fixture-only-password -file "$scratch/certificate.crt" >> "$scratch/keytool.log" 2>&1
keytool -J-Xmx96m -J-XX:+UseSerialGC -importcert -noprompt -alias fixture -keystore "$scratch/trust.p12" \
  -storepass fixture-only-password -storetype PKCS12 -file "$scratch/certificate.crt" >> "$scratch/keytool.log" 2>&1
java -Xmx256m -XX:+UseSerialGC -XX:ActiveProcessorCount=1 -Djava.io.tmpdir="${TMPDIR:-/tmp}" \
  -Djavax.net.ssl.trustStore="$scratch/trust.p12" -Djavax.net.ssl.trustStorePassword=fixture-only-password \
  --add-modules jdk.httpserver -cp "$runtime" \
  io.github.siberanka.vibecraft.bridge.PaperLifecycleTests "$scratch/tls.p12"

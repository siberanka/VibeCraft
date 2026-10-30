#!/bin/sh
# Offline build: obtain the exact provided API jars listed in dependencies.sha256 first.
set -eu
cd "$(dirname "$0")"
base=$(pwd -P)
deps=${1:?Usage: sh build.sh /absolute/path/to/provided-api-jars}
case "$deps" in /*) ;; *) echo 'The dependency directory must be absolute.' >&2; exit 2 ;; esac
test "$(javac -version 2>&1 | cut -d ' ' -f 2 | cut -d . -f 1)" = 25 || { echo 'JDK 25 is required.' >&2; exit 2; }
(cd "$deps"; sha256sum -c "$base/dependencies.sha256")
classpath=
while read -r digest name; do
  classpath=${classpath:+$classpath:}$deps/$name
done < dependencies.sha256
test ! -e target || { echo 'Use a clean checkout without target/ before building.' >&2; exit 2; }
mkdir -p target/classes target/test-classes
find src/main/java -name '*.java' -type f | LC_ALL=C sort > target/main-sources.txt
javac -J-Xmx128m -J-XX:+UseSerialGC -J-XX:ActiveProcessorCount=1 --release 25 -proc:none -Xlint:deprecation,unchecked -Werror \
  -cp "$classpath" -d target/classes @target/main-sources.txt
find src/test/java -name '*.java' -type f | LC_ALL=C sort > target/test-sources.txt
javac -J-Xmx128m -J-XX:+UseSerialGC -J-XX:ActiveProcessorCount=1 --release 25 -proc:none -Xlint:deprecation,unchecked -Werror \
  -cp "target/classes:$classpath" -d target/test-classes @target/test-sources.txt
java -Djava.io.tmpdir="${TMPDIR:-/tmp}" -Xmx128m -XX:+UseSerialGC -XX:ActiveProcessorCount=1 -cp "target/classes:target/test-classes:$classpath" \
  io.github.siberanka.vibecraft.bridge.CoreTests
cp src/main/resources/plugin.yml src/main/resources/config.yml LICENSE NOTICE.md target/classes/
jar --create --file target/VibeCraftBridge-0.9.0-beta.jar --date=2026-10-27T00:00:00Z -C target/classes .
sha256sum target/VibeCraftBridge-0.9.0-beta.jar
printf 'PAPER_BRIDGE_BUILD_PASS\n'

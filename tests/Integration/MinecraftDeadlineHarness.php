<?php

/** One bounded local peer at a time; invoke only in the isolated Linux QA profile. */
if (PHP_SAPI !== 'cli' || PHP_OS_FAMILY !== 'Linux' || getenv('APP_ENV') !== 'testing'
    || ! str_starts_with((string) getenv('HOME'), '/scratch/qa/')
    || ! str_starts_with(sys_get_temp_dir(), '/scratch/qa/') || ! function_exists('pcntl_fork')) {
    fwrite(STDERR, "Minecraft deadline harness requires the dedicated isolated Linux QA profile.\n");
    exit(2);
}
$project = dirname(__DIR__, 2);
require $project.'/tests/Fixtures/LoopbackEndpoint.php';
if (class_exists(App\Core\Security\PublicEndpoint::class, false)) {
    throw new \RuntimeException('The production endpoint resolver must not be preloaded in this fixture.');
}
class_alias(Tests\Fixtures\LoopbackEndpoint::class, App\Core\Security\PublicEndpoint::class, false);
foreach (['NetworkDeadline', 'StatusResult', 'MotdText', 'JavaPinger'] as $class) {
    require $project.'/app/Domain/Minecraft/'.$class.'.php';
}

function varint(int $value): string
{
    $out = '';
    do {
        $byte = $value & 0x7f;
        $value >>= 7;
        $out .= chr($value ? ($byte | 0x80) : $byte);
    } while ($value);

    return $out;
}

function pingCase(string $name, float $budget, int $delay): array
{
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (! $server) {
        throw new \RuntimeException('Fixture listener failed.');
    }
    $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
    $json = json_encode(['version' => ['name' => 'QA'], 'players' => ['online' => 1, 'max' => 2], 'description' => 'local dummy'], JSON_THROW_ON_ERROR);
    $pid = pcntl_fork();
    if ($pid === -1) {
        throw new \RuntimeException('Fixture fork failed.');
    }
    if ($pid === 0) {
        pcntl_alarm(4);
        $peer = stream_socket_accept($server, 1);
        if (! $peer) {
            exit(3);
        }
        stream_set_timeout($peer, 0, 100000);
        fread($peer, 1024);
        $payload = "\x00".varint(strlen($json)).$json;
        fwrite($peer, varint(strlen($payload))."\x00".varint(strlen($json)));
        if ($delay === 0) {
            fwrite($peer, $json);
        } else {
            for ($index = 0; $index < strlen($json); $index++) {
                if (@fwrite($peer, $json[$index]) !== 1) {
                    break;
                }
                usleep($delay);
            }
        }
        fclose($peer);
        fclose($server);
        exit(0);
    }
    fclose($server);
    pcntl_alarm(5);
    $began = hrtime(true);
    $result = (new \App\Domain\Minecraft\JavaPinger())->ping('127.0.0.1', $port, $budget);
    $elapsed = (hrtime(true) - $began) / 1e9;
    pcntl_waitpid($pid, $status);
    pcntl_alarm(0);

    return ['case' => $name, 'configured_timeout_seconds' => $budget, 'elapsed_seconds' => round($elapsed, 4),
        'online' => $result->online, 'players' => $result->players, 'latency_ms' => $result->latencyMs,
        'peer_json_bytes' => strlen($json), 'peer_per_byte_delay_microseconds' => $delay,
        'peer_exited' => pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0];
}

$slow = pingCase('slow_drip_deadline', 0.05, 15000);
$fast = pingCase('valid_immediate_response', 0.25, 0);
$passed = ! $slow['online'] && $slow['elapsed_seconds'] < 0.2 && $slow['peer_exited']
    && $fast['online'] && $fast['players'] === 1 && $fast['elapsed_seconds'] < 0.5 && $fast['peer_exited'];
echo json_encode(['status' => $passed ? 'passed' : 'failed', 'fixture' => 'isolated local peers; PublicEndpoint stubbed only for loopback',
    'cases' => [$slow, $fast], 'java_pinger_sha256' => hash_file('sha256', $project.'/app/Domain/Minecraft/JavaPinger.php'),
    'deadline_sha256' => hash_file('sha256', $project.'/app/Domain/Minecraft/NetworkDeadline.php')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
exit($passed ? 0 : 1);

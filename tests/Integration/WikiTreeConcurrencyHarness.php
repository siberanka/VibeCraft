<?php

/** Dedicated offline QA only. Calls the actual editor with independent MariaDB workers. */
use App\Http\Controllers\AdminWikiController;
use App\Models\User;
use App\Models\WikiArticle;
use App\Models\WikiCategory;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (PHP_OS_FAMILY !== 'Linux' || ! function_exists('pcntl_fork') || ! function_exists('pcntl_alarm')
    || config('database.default') !== 'mariadb' || config('database.connections.mariadb.database') !== 'vibecraft_qa'
    || ! str_starts_with((string) config('database.connections.mariadb.unix_socket'), '/scratch/qa/')) {
    fwrite(STDERR, "Use the dedicated offline Linux/MariaDB QA fixture.\n");
    exit(2);
}
$owner = User::where('email', 'owner@fixture.example.test')->firstOrFail();
$run = 'qa_tree_'.bin2hex(random_bytes(5));
$scratch = '/scratch/qa/'.$run;
if (! mkdir($scratch, 0700)) {
    throw new RuntimeException('Cannot create assigned QA scratch.');
}

function treeCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function treeWait(string $path, float $seconds): bool
{
    $deadline = microtime(true) + $seconds;
    do {
        clearstatcache(true, $path);
        if (is_file($path)) {
            return true;
        }
        usleep(2000);
    } while (microtime(true) < $deadline);

    return false;
}

function treeArticle(WikiCategory $category, string $slug): WikiArticle
{
    return WikiArticle::create([
        'wiki_category_id' => $category->id, 'locale' => 'tr', 'title' => 'Local tree fixture', 'slug' => $slug,
        'body_markdown' => 'A local concurrency fixture.', 'body_html' => '<p>A local concurrency fixture.</p>',
        'table_of_contents' => [], 'status' => 'draft', 'sort' => 0,
    ])->refresh();
}

function treeSave(int $articleId, int $ownerId, array $changes): void
{
    $article = WikiArticle::findOrFail($articleId);
    $data = array_replace($article->only(['wiki_category_id', 'parent_id', 'locale', 'title', 'slug', 'summary', 'body_markdown', 'status', 'sort', 'revision']), $changes);
    $request = Request::create('/admin/wiki/articles/'.$articleId, 'PUT', $data);
    $request->setUserResolver(fn () => User::findOrFail($ownerId));
    app(AdminWikiController::class)->saveArticle($request, $article);
}

/** Only the two owned children write bounded primitive outcomes; no SQL/messages retained. */
function treeRace(string $name, callable $work): array
{
    global $scratch;
    $directory = $scratch.'/'.$name;
    mkdir($directory, 0700);
    DB::purge();
    $children = [];
    for ($worker = 0; $worker < 2; $worker++) {
        $pid = pcntl_fork();
        treeCheck($pid !== -1, 'QA fork failed.');
        if ($pid === 0) {
            pcntl_alarm(12);
            if (! treeWait($directory.'/go', 3)) {
                exit(3);
            }
            try {
                DB::purge();
                DB::reconnect();
                DB::statement('SET SESSION innodb_lock_wait_timeout = 5');
                $work($worker, $directory);
                $result = ['outcome' => 'saved'];
            } catch (ValidationException $exception) {
                $result = ['outcome' => 'rejected', 'fields' => array_keys($exception->errors())];
            } catch (Throwable $exception) {
                $result = ['outcome' => 'error', 'type' => get_class($exception)];
            }
            file_put_contents($directory.'/'.$worker.'.json', json_encode($result, JSON_THROW_ON_ERROR));
            DB::purge();
            exit(0);
        }
        $children[] = $pid;
    }
    file_put_contents($directory.'/go', 'go');
    $results = [];
    foreach ($children as $worker => $pid) {
        pcntl_waitpid($pid, $status);
        treeCheck(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0, 'Owned bounded worker failed.');
        $results[$worker] = json_decode(file_get_contents($directory.'/'.$worker.'.json'), true, 8, JSON_THROW_ON_ERROR);
    }
    DB::purge();
    DB::reconnect();
    if (in_array('error', array_column($results, 'outcome'), true)) {
        echo json_encode(['failed_race' => $name, 'workers' => $results], JSON_THROW_ON_ERROR).PHP_EOL;
    }
    treeCheck(! in_array('error', array_column($results, 'outcome'), true), 'Unexpected application/database race failure.');

    return $results;
}

$firstCategory = WikiCategory::create(['locale' => 'tr', 'name' => 'Local race fixture', 'slug' => $run.'-a', 'is_visible' => false]);
$secondCategory = WikiCategory::create(['locale' => 'tr', 'name' => 'Local race fixture', 'slug' => $run.'-b', 'is_visible' => false]);
$a = treeArticle($firstCategory, $run.'-cycle-a');
$b = treeArticle($firstCategory, $run.'-cycle-b');
$opposite = treeRace('opposite', function (int $worker) use ($a, $b, $owner): void {
    treeSave($worker === 0 ? $a->id : $b->id, $owner->id, ['parent_id' => $worker === 0 ? $b->id : $a->id]);
});
$counts = array_count_values(array_column($opposite, 'outcome'));
treeCheck(($counts['saved'] ?? 0) === 1 && ($counts['rejected'] ?? 0) === 1, 'Opposite reparent did not serialize into one valid result.');
treeCheck(! ($a->fresh()->parent_id === $b->id && $b->fresh()->parent_id === $a->id), 'A cycle was committed.');

$moving = treeArticle($firstCategory, $run.'-moving');
$attaching = treeArticle($firstCategory, $run.'-attaching');
$crossCategory = treeRace('move-attach', function (int $worker, string $directory) use ($moving, $attaching, $owner, $secondCategory): void {
    if ($worker === 0) {
        $paused = false;
        DB::listen(function (QueryExecuted $event) use (&$paused, $directory, $moving): void {
            // Pause after the final empty-subtree check, while the actual editor
            // still holds structure/category/article locks and before its write.
            if (! $paused && str_contains($event->sql, 'select exists') && str_contains($event->sql, '`wiki_articles`')
                && str_contains($event->sql, '`parent_id` = ?') && count($event->bindings) === 1
                && (int) $event->bindings[0] === $moving->id) {
                $paused = true;
                file_put_contents($directory.'/validated', 'ready');
                treeCheck(treeWait($directory.'/attempted', 3), 'Second editor did not start.');
                usleep(1000000);
            }
        });
        treeSave($moving->id, $owner->id, ['wiki_category_id' => $secondCategory->id, 'parent_id' => null]);
        treeCheck($paused, 'The coordinated validation boundary was not reached.');
    } else {
        treeCheck(treeWait($directory.'/validated', 3), 'Moving editor did not reach validation boundary.');
        file_put_contents($directory.'/attempted', 'started');
        treeSave($attaching->id, $owner->id, ['parent_id' => $moving->id]);
    }
});
treeCheck($crossCategory[0]['outcome'] === 'saved' && $crossCategory[1]['outcome'] === 'rejected', 'Cross-category move/attach did not revalidate after serialization.');
treeCheck((int) $moving->fresh()->wiki_category_id === $secondCategory->id && $attaching->fresh()->parent_id === null, 'A cross-category edge was committed.');
echo 'WIKI_TREE_CONCURRENCY_PASS independent_workers=2 opposite_reparent=serialized cross_category_move_attach=serialized category_edges=consistent'.PHP_EOL;

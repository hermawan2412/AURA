<?php

// Run: php tests/auth-session.php (requires PDO SQLite).
// Execute actual source with temporary config/session storage, never real credentials or DB.
namespace Aurat;

use PDO;
use RuntimeException;
use Throwable;

class Database
{
    public static $connection;

    public static function pdo()
    {
        return self::$connection;
    }
}

function expect($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = sys_get_temp_dir() . '/aura-auth-test-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
foreach (array('src', 'config', 'vendor', 'sessions') as $directory) {
    mkdir($root . '/' . $directory, 0700);
}
foreach (array('Auth.php', 'Csrf.php', 'bootstrap.php') as $file) {
    copy(__DIR__ . '/../src/' . $file, $root . '/src/' . $file);
}
file_put_contents($root . '/config/config.php', '<?php return array("session_name" => "AURATEST", "max_percobaan_gagal" => 5, "lama_kunci_menit" => 15);');
file_put_contents($root . '/vendor/autoload.php', '<?php');
require $root . '/src/Auth.php';
require $root . '/src/Csrf.php';
ini_set('session.save_handler', 'files');
ini_set('session.save_path', $root . '/sessions');
ini_set('session.gc_probability', '0');

$options = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC);
if (class_exists('Pdo\\Sqlite')) {
    $pdo = new \Pdo\Sqlite('sqlite::memory:', null, null, $options);
    $pdo->createFunction('NOW', function () { return date('Y-m-d H:i:s'); });
} else {
    $pdo = new PDO('sqlite::memory:', null, null, $options);
    $pdo->sqliteCreateFunction('NOW', function () { return date('Y-m-d H:i:s'); });
}
$pdo->exec('CREATE TABLE user_login (id INTEGER PRIMARY KEY, username TEXT, password_hash TEXT, status_aktif INTEGER, terkunci_hingga TEXT, percobaan_gagal INTEGER, nama_tampilan TEXT, peran TEXT, login_terakhir_at TEXT)');
Database::$connection = $pdo;

$startSession = function ($id = '') use ($root) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $_SESSION = array();
    session_id($id);
    require $root . '/src/bootstrap.php';
};

$tests = array();
$tests['successful login rotates ID and preserves authenticated session'] = function () use ($startSession, $pdo) {
    $oldId = session_id();
    expect(Auth::login('tester', 'correct-password')['success'], 'Valid credentials rejected');
    expect(session_id() !== $oldId, 'Successful login retained the anonymous session ID');
    $newId = session_id();
    $startSession($newId);
    expect(session_id() === $newId, 'Authenticated session did not persist');
    expect($_SESSION['user_id'] === 1 && $_SESSION['username'] === 'tester' && $_SESSION['nama_tampilan'] === 'Test User' && $_SESSION['peran'] === 'admin', 'Authenticated identity missing or incorrect');
    $user = $pdo->query('SELECT * FROM user_login WHERE id = 1')->fetch();
    expect((int) $user['percobaan_gagal'] === 0 && $user['terkunci_hingga'] === null && !empty($user['login_terakhir_at']), 'Successful login bookkeeping regressed');
};
$tests['successful login invalidates the anonymous CSRF token'] = function () {
    $oldToken = Csrf::token();
    expect(Auth::login('tester', 'correct-password')['success'], 'Valid credentials rejected');
    $newToken = Csrf::token();
    expect($oldToken !== $newToken && strlen($newToken) === 64, 'Anonymous CSRF token survived authentication');
};
$tests['old session is deleted and cannot recover authenticated identity'] = function () use ($startSession, $root) {
    $oldId = session_id();
    $_SESSION['anonymous_marker'] = 'original-session';
    session_write_close();
    $startSession($oldId);
    expect(Auth::login('tester', 'correct-password')['success'], 'Valid credentials rejected');
    session_write_close();
    expect(!file_exists($root . '/sessions/sess_' . $oldId), 'Old session storage was not deleted');
    $startSession($oldId);
    expect(session_id() !== $oldId && !Auth::check() && !isset($_SESSION['anonymous_marker']), 'Old ID restored the original session');
};
foreach (array('wrong password', 'missing user', 'inactive user', 'locked user') as $scenario) {
    $tests[$scenario . ' does not authenticate or rotate session/token'] = function () use ($scenario, $pdo) {
        $username = 'tester';
        $password = 'correct-password';
        if ($scenario === 'wrong password') {
            $password = 'incorrect-password';
        } elseif ($scenario === 'missing user') {
            $username = 'absent';
        } elseif ($scenario === 'inactive user') {
            $pdo->exec('UPDATE user_login SET status_aktif = 0');
        } else {
            $stmt = $pdo->prepare('UPDATE user_login SET terkunci_hingga = ?');
            $stmt->execute(array(date('Y-m-d H:i:s', time() + 3600)));
        }
        $oldId = session_id();
        Csrf::token();
        $before = $_SESSION;
        expect(!Auth::login($username, $password)['success'], 'Invalid login accepted');
        expect(!Auth::check() && session_id() === $oldId && $_SESSION === $before, 'Failed login changed authentication/session/token');
        if ($scenario === 'wrong password') {
            expect((int) $pdo->query('SELECT percobaan_gagal FROM user_login')->fetchColumn() === 3, 'Failed attempt was not recorded');
        }
    };
}
$tests['session regeneration failure cannot authenticate'] = function () {
    Csrf::token();
    $before = $_SESSION;
    session_write_close();
    // No active session forces the real PHP regeneration function to fail.
    $result = @Auth::login('tester', 'correct-password');
    expect(!$result['success'] && !Auth::check() && $_SESSION === $before, 'Login elevated identity without successful regeneration');
};
$tests['bootstrap rejects an uninitialized session ID'] = function () use ($startSession) {
    session_write_close();
    ini_set('session.use_strict_mode', '0');
    $uninitializedId = 'attacker-chosen-uninitialized-id';
    $startSession($uninitializedId);
    expect(session_id() !== $uninitializedId, 'Bootstrap accepted an uninitialized session ID');
};

$failures = 0;
$output = array();
try {
    foreach ($tests as $name => $test) {
        $pdo->exec('DELETE FROM user_login');
        $stmt = $pdo->prepare('INSERT INTO user_login VALUES (1, ?, ?, 1, NULL, 2, ?, ?, NULL)');
        $stmt->execute(array('tester', password_hash('correct-password', PASSWORD_DEFAULT), 'Test User', 'admin'));
        $startSession();
        try {
            $test();
            $output[] = 'PASS ' . $name;
        } catch (Throwable $error) {
            ++$failures;
            $output[] = 'FAIL ' . $name . ': ' . $error->getMessage();
        }
    }
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    foreach (array('src', 'config', 'vendor', 'sessions') as $directory) {
        foreach (glob($root . '/' . $directory . '/*') as $file) {
            unlink($file);
        }
        rmdir($root . '/' . $directory);
    }
    rmdir($root);
}
echo implode(PHP_EOL, $output) . PHP_EOL;
echo count($tests) . ' tests, ' . $failures . ' failures' . PHP_EOL;
exit($failures === 0 ? 0 : 1);

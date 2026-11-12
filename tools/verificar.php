<?php
/**
 * Verificador previo a subir cambios de ContaPro.
 *
 * Uso:  php tools/verificar.php            -> sintaxis + rutas/métodos
 *       php tools/verificar.php --smoke    -> además levanta `php -S` y recorre las páginas/APIs GET
 *
 * Devuelve código de salida 0 si todo está bien y 1 si encontró errores.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);
$smoke = in_array('--smoke', $argv, true);
$errores = 0;

function titulo(string $t): void { echo "\n== {$t} ==\n"; }
function ok(string $t): void     { echo "  [OK]    {$t}\n"; }
function fallo(string $t): void  { echo "  [ERROR] {$t}\n"; }

// ---------------------------------------------------------------------------
// 1. Sintaxis de todos los .php (php -l)
// ---------------------------------------------------------------------------
titulo('1. Sintaxis PHP (php -l)');
$archivos = ['index.php'];
foreach (['app', 'core', 'config', 'database', 'views'] as $dir) {
    if (!is_dir($dir)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
            $archivos[] = str_replace('\\', '/', $f->getPathname());
        }
    }
}
$fallosSintaxis = 0;
foreach ($archivos as $f) {
    $salida = [];
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($f) . ' 2>&1', $salida, $code);
    if ($code !== 0) {
        $fallosSintaxis++;
        $msg = trim(implode(' ', array_filter($salida, fn($l) => stripos($l, 'error') !== false)));
        fallo("{$f}: {$msg}");
    }
}
$errores += $fallosSintaxis;
$fallosSintaxis === 0 ? ok(count($archivos) . ' archivos sin errores de sintaxis') : null;

// Si hay errores de sintaxis no tiene sentido cargar las clases.
if ($fallosSintaxis > 0) {
    echo "\nRESULTADO: {$errores} error(es). Corrija la sintaxis antes de continuar.\n";
    exit(1);
}

// ---------------------------------------------------------------------------
// 2. Rutas y llamadas estáticas apuntan a métodos que existen
// ---------------------------------------------------------------------------
titulo('2. Rutas y llamadas a métodos');
spl_autoload_register(function (string $class) use ($root): void {
    foreach (['Core\\' => '/core/', 'App\\' => '/app/'] as $prefix => $base) {
        if (strncmp($prefix, $class, strlen($prefix)) === 0) {
            $file = $root . $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) require_once $file;
            return;
        }
    }
});

/** Resuelve un nombre corto de clase usando los `use` y el namespace del archivo. */
function resolverClase(string $nombre, array $uses, string $ns): ?string {
    if (isset($uses[$nombre])) return $uses[$nombre];
    if (str_contains($nombre, '\\')) return ltrim($nombre, '\\');
    $candidato = $ns !== '' ? $ns . '\\' . $nombre : $nombre;
    return class_exists($candidato) ? $candidato : null;
}

$fallosMetodos = 0;
$revisadas = 0;
$vistos = [];
foreach ($archivos as $f) {
    if (str_starts_with($f, 'views/') || str_starts_with($f, 'config/') || str_starts_with($f, 'database/')) continue;
    $src = file_get_contents($f);
    $ns = preg_match('/^namespace\s+([\w\\\\]+);/m', $src, $m) ? $m[1] : '';
    $claseActual = preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+(\w+)/m', $src, $m) ? ($ns !== '' ? "{$ns}\\{$m[1]}" : $m[1]) : null;
    $uses = [];
    if (preg_match_all('/^use\s+([\w\\\\]+?)(?:\s+as\s+(\w+))?;/m', $src, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $u) {
            $alias = $u[2] ?? '';
            $uses[$alias !== '' ? $alias : substr(strrchr('\\' . $u[1], '\\'), 1)] = $u[1];
        }
    }

    $llamadas = [];
    // [Clase::class, 'metodo']  (tablas de rutas)
    if (preg_match_all("/\[\s*([\\w\\\\]+)::class\s*,\s*'(\w+)'\s*\]/", $src, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($mm as $c) $llamadas[] = [$c[1][0], $c[2][0], $c[0][1]];
    }
    // Clase::metodo(  /  self::metodo(  /  static::metodo(
    if (preg_match_all('/(?<![\w$>\\\\])(\\\\?[A-Z][\w\\\\]*|self|static)::([a-zA-Z_]\w*)\s*\(/', $src, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($mm as $c) $llamadas[] = [$c[1][0], $c[2][0], $c[0][1]];
    }

    foreach ($llamadas as [$clase, $metodo, $offset]) {
        $fqcn = in_array($clase, ['self', 'static'], true) ? $claseActual : resolverClase($clase, $uses, $ns);
        // Solo verificamos clases propias del proyecto (App\*, Core\*)
        if ($fqcn === null || !preg_match('/^(App|Core)\\\\/', $fqcn)) continue;
        $linea = substr_count(substr($src, 0, $offset), "\n") + 1;
        $clave = "{$f}:{$linea}:{$fqcn}::{$metodo}";
        if (isset($vistos[$clave])) continue;
        $vistos[$clave] = true;
        $revisadas++;
        if (!class_exists($fqcn)) {
            $fallosMetodos++;
            fallo("{$f}:{$linea} clase inexistente {$fqcn}");
        } elseif (!method_exists($fqcn, $metodo)) {
            $fallosMetodos++;
            fallo("{$f}:{$linea} método inexistente {$fqcn}::{$metodo}()");
        } elseif (!(new ReflectionMethod($fqcn, $metodo))->isStatic() && !in_array($clase, ['self', 'static'], true)) {
            $fallosMetodos++;
            fallo("{$f}:{$linea} {$fqcn}::{$metodo}() no es static y se llama de forma estática");
        }
    }
}
$errores += $fallosMetodos;
$fallosMetodos === 0 ? ok("{$revisadas} llamadas/rutas verificadas") : null;

// ---------------------------------------------------------------------------
// 3. Prueba de humo con el servidor embebido (opcional)
// ---------------------------------------------------------------------------
if ($smoke) {
    titulo('3. Prueba de humo (php -S, sin sesión)');
    // Sin MySQL cada petición tarda ~2 s en fallar la conexión y algunas rutas dan 500: no es representativo.
    $dbCfg = require $root . '/config/database.php';
    $dbSock = @fsockopen((string)$dbCfg['host'], (int)$dbCfg['port'], $en, $es, 1.5);
    if (!$dbSock) {
        echo "  [AVISO] MySQL no responde en {$dbCfg['host']}:{$dbCfg['port']}. Inicie MySQL (Laragon) y vuelva a ejecutar con --smoke.\n";
        $smoke = false;
    } else {
        fclose($dbSock);
    }
}
if ($smoke) {
    $index = file_get_contents('index.php');
    $urls = ['/', '/login', '/logout', '/menu', '/superadmin', '/api/session/status'];
    // Rutas exactas:  $uri === '/...'
    if (preg_match_all("/\\\$uri === '(\/[^']*)'/", $index, $mm)) $urls = array_merge($urls, $mm[1]);
    // Tablas de APIs:  '/api/...' => [...]
    if (preg_match_all("/^\s*'(\/api\/[^']+)'\s*=>/m", $index, $mm)) $urls = array_merge($urls, $mm[1]);
    // Formularios /app/{forma}
    if (preg_match_all("/^\s*'([A-Z0-9_\-]+)'\s*=>\s*\[/m", $index, $mm)) {
        foreach ($mm[1] as $slug) $urls[] = '/app/' . $slug;
    }
    $urls = array_values(array_unique($urls));

    $puerto = 8790 + random_int(0, 99);
    $log = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "contapro_smoke_{$puerto}.log";
    $cmd = escapeshellarg(PHP_BINARY) . " -d display_errors=stderr -S 127.0.0.1:{$puerto} index.php";
    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']], $pipes, $root);
    if (!is_resource($proc)) {
        fallo('No se pudo iniciar php -S');
        $errores++;
    } else {
        // Esperar a que el servidor acepte conexiones
        for ($i = 0; $i < 50; $i++) {
            $s = @fsockopen('127.0.0.1', $puerto, $en, $es, 0.2);
            if ($s) { fclose($s); break; }
            usleep(100000);
        }
        $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 20]]);
        $fallosHumo = 0;
        $estados = [];
        foreach ($urls as $u) {
            $body = @file_get_contents("http://127.0.0.1:{$puerto}{$u}", false, $ctx);
            $hdr = $http_response_header ?? [];
            $status = isset($hdr[0]) && preg_match('/\s(\d{3})\s?/', $hdr[0], $sm) ? (int)$sm[1] : 0;
            $estados[$status] = ($estados[$status] ?? 0) + 1;
            $fatal = is_string($body) && preg_match('/(Fatal error|Parse error|Uncaught \w+)/i', $body);
            if ($status === 0 || $status >= 500 || $fatal) {
                $fallosHumo++;
                fallo("GET {$u} -> HTTP {$status}" . ($fatal ? ' (error PHP en la respuesta)' : ''));
            }
        }
        proc_terminate($proc);
        proc_close($proc);
        $logTxt = is_file($log) ? (string)file_get_contents($log) : '';
        if (preg_match_all('/PHP (Fatal error|Parse error|Warning|Deprecated):.*$/m', $logTxt, $lm)) {
            foreach (array_unique($lm[0]) as $l) echo "  [AVISO] {$l}\n";
        }
        @unlink($log);
        $errores += $fallosHumo;
        ksort($estados);
        $resumen = implode(', ', array_map(fn($k, $v) => "HTTP {$k}: {$v}", array_keys($estados), $estados));
        $fallosHumo === 0 ? ok(count($urls) . " URLs sin errores 500 ({$resumen})") : null;
    }
}

echo "\n";
if ($errores > 0) {
    echo "RESULTADO: {$errores} error(es) encontrados. NO suba los cambios todavía.\n";
    exit(1);
}
echo "RESULTADO: todo correcto.\n";
exit(0);

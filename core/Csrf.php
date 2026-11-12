<?php
namespace Core;

/**
 * Protección CSRF (Cross-Site Request Forgery).
 *
 * - Cada sesión tiene un token aleatorio en $_SESSION['csrf_token'].
 * - Toda página HTML recibe un script (inyectado en <head>) que agrega la cabecera
 *   X-CSRF-Token a cada fetch/XHR del mismo origen (incluido jQuery).
 * - El servidor exige el token en:
 *     · cualquier petición que no sea GET/HEAD/OPTIONS, y
 *     · cualquier petición a /api/* (también GET, porque los controladores leen $_GET
 *       y un simple enlace podría disparar un borrado), salvo las descargas que el
 *       navegador abre navegando (Excel, backup, XML), que son de solo lectura.
 * - Si el token no coincide (p.ej. la sesión expiró con la página abierta), responde 403
 *   con el token vigente en la cabecera X-CSRF-Token y el script reintenta una sola vez.
 */
class Csrf {

    private const SESSION_KEY = 'csrf_token';
    private const HEADER      = 'HTTP_X_CSRF_TOKEN';

    /** Descargas GET de solo lectura que se abren con window.open / enlaces (no pueden enviar cabeceras). */
    private const GET_EXENTAS = '#^/api/(?:backup/download/.+|sifen/descargar-xml/.+|formulario-(?:120|515)/exportar-xls|reportes/(?:libro-compras|libro-ventas|resumen-irp)/excel)$#';

    public static function token(): string {
        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function requiereToken(string $uri, string $method): bool {
        if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }
        if (str_starts_with($uri, '/api/')) {
            return !preg_match(self::GET_EXENTAS, $uri);
        }
        return false;
    }

    public static function esValido(): bool {
        $enviado = $_SERVER[self::HEADER] ?? ($_POST['_csrf'] ?? '');
        $actual  = $_SESSION[self::SESSION_KEY] ?? '';
        return is_string($enviado) && is_string($actual) && $actual !== '' && hash_equals($actual, $enviado);
    }

    /** Corta la petición con 403 si requiere token y no es válido. */
    public static function proteger(string $uri, string $method): void {
        if (!self::requiereToken($uri, $method) || self::esValido()) {
            return;
        }
        header('X-CSRF-Token: ' . self::token());
        header('X-CSRF-Failed: 1');
        View::json([
            'status'  => 'error',
            'success' => false,
            'message' => 'La sesión de seguridad expiró. Recargue la página (F5) e intente nuevamente.',
        ], 403);
        exit;
    }

    /** Script que agrega la cabecera X-CSRF-Token a fetch y XMLHttpRequest del mismo origen. */
    public static function scriptCliente(): string {
        $t = self::token(); // hexadecimal: seguro dentro del literal JS
        return "<script>(function(){window.CSRF_TOKEN='{$t}';"
            . "function same(u){try{return new URL(String(u),location.href).origin===location.origin;}catch(e){return false;}}"
            . "var oF=window.fetch;if(oF){window.fetch=function(i,o){"
            . "var url=(typeof Request!=='undefined'&&i instanceof Request)?i.url:i;"
            . "if(!same(url)){return oF.apply(this,arguments);}"
            . "o=Object.assign({},o||{});var h=new Headers(o.headers||((typeof Request!=='undefined'&&i instanceof Request)?i.headers:undefined));"
            . "h.set('X-CSRF-Token',window.CSRF_TOKEN);o.headers=h;var self=this;"
            . "return oF.call(self,i,o).then(function(r){var n=r.headers.get('X-CSRF-Token');"
            . "if(r.status===403&&n&&r.headers.get('X-CSRF-Failed')&&!o.__cpRetry){window.CSRF_TOKEN=n;h.set('X-CSRF-Token',n);o.__cpRetry=1;return oF.call(self,i,o);}"
            . "return r;});};}"
            . "var X=XMLHttpRequest.prototype,oO=X.open,oS=X.send,oH=X.setRequestHeader;"
            . "X.open=function(m,u){this.__cpSame=same(u);this.__cpTok=false;return oO.apply(this,arguments);};"
            . "X.setRequestHeader=function(k){if(String(k).toLowerCase()==='x-csrf-token'){this.__cpTok=true;}return oH.apply(this,arguments);};"
            . "X.send=function(){if(this.__cpSame&&!this.__cpTok){try{oH.call(this,'X-CSRF-Token',window.CSRF_TOKEN);}catch(e){}}return oS.apply(this,arguments);};"
            . "})();</script>";
    }

    /** Inyecta el script al inicio de <head> en respuestas HTML. */
    public static function inyectarEnHtml(string $buf): string {
        if (stripos($buf, '<head') === false) {
            return $buf;
        }
        return preg_replace('/<head(\b[^>]*)>/i', '<head$1>' . self::scriptCliente(), $buf, 1) ?? $buf;
    }
}

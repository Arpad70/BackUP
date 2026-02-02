<?php
// Stubs for ssh2 extension so static analyzers (Intelephense) don't warn when the extension
// is not installed. These are no-op definitions and are guarded by extension check so they
// only exist for static analysis.

if (!extension_loaded('ssh2')) {
    /**
     * @param string $host
     * @param int $port
     * @param array|null $methods
     * @param array|null $callbacks
     * @return resource|false
     */
    function ssh2_connect(string $host, int $port = 22, array $methods = null, array $callbacks = null) { }

    /**
     * @param resource $session
     * @param string $username
     * @param string $password
     * @return bool
     */
    function ssh2_auth_password($session, string $username, string $password) { }

    /**
     * @param resource $session
     * @return resource|false
     */
    function ssh2_sftp($session) { }
}

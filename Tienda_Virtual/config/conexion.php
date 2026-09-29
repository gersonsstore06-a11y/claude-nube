<?php
require_once __DIR__ . '/app.php';

class Conexion
{
    private string $host;
    private int $port;
    private string $db;
    private string $user;
    private string $pass;
    private string $charset;

    public function __construct()
    {
        $this->host = (string) appConfigGet('db.host', '127.0.0.1');
        $this->port = (int) appConfigGet('db.port', 3307);
        $this->db = (string) appConfigGet('db.name', 'tienda_virtual');
        $this->user = (string) appConfigGet('db.user', 'root');
        $this->pass = (string) appConfigGet('db.pass', '');
        $this->charset = (string) appConfigGet('db.charset', 'utf8mb4');
    }

    public function getConexion(): PDO
    {
        $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db};charset={$this->charset}";

        return new PDO($dsn, $this->user, $this->pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}

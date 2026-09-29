<?php
class Usuario
{
    public function __construct(
        public ?int $id = null,
        public string $nombre = '',
        public string $email = '',
        public string $rol = 'cliente'
    ) {
    }
}

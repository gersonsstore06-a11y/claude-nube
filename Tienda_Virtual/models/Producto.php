<?php
class Producto
{
    public function __construct(
        public ?int $id = null,
        public string $nombre = '',
        public float $precio = 0.0,
        public int $stock = 0
    ) {
    }
}

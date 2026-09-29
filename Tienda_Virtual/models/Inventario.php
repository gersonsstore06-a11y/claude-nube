<?php
class Inventario
{
    public function __construct(
        public ?int $id = null,
        public int $productoId = 0,
        public int $stockActual = 0,
        public int $stockMinimo = 0
    ) {
    }
}

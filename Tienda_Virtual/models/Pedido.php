<?php
class Pedido
{
    public function __construct(
        public ?int $id = null,
        public int $clienteId = 0,
        public float $total = 0.0,
        public string $estado = 'pendiente'
    ) {
    }
}

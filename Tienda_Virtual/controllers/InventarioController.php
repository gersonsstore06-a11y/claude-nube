<?php
class InventarioController
{
    public function actualizarStock(int $productoId, int $cantidad): bool
    {
        return $productoId > 0 && $cantidad >= 0;
    }
}

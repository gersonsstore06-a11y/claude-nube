<?php
class ProductoController
{
    public function listar(): array
    {
        return [];
    }

    public function obtenerPorId(int $id): ?array
    {
        return $id > 0 ? ['id' => $id] : null;
    }
}

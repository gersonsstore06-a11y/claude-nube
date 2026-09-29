<?php
class PedidoController
{
    public function listarPorCliente(int $clienteId): array
    {
        return $clienteId > 0 ? [] : [];
    }

    public function crear(array $data): bool
    {
        return !empty($data);
    }
}

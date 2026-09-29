# Instalacion y uso en otras PCs o telefonos

## Configuracion local

1. Crea `config/local.php` a partir de `config/local.example.php`.
2. Ajusta `app.base_url` si tu proyecto queda en otra carpeta del servidor.
3. Ajusta `db.host`, `db.port`, `db.name`, `db.user` y `db.pass` segun la base de datos de esa PC.
4. Si necesitas otro acceso admin, cambia `admin.email` y `admin.password_hash`.

## Alternativa con variables de entorno

La app tambien acepta estas variables:

- `APP_NAME`
- `APP_BASE_URL`
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DB_CHARSET`
- `ADMIN_AUTH_EMAIL`
- `ADMIN_AUTH_NAME`
- `ADMIN_AUTH_PASSWORD_HASH`

## Acceso desde telefonos

1. Usa la IP local de la PC donde corre Apache, por ejemplo `http://192.168.1.50/Tienda_Virtual`.
2. Asegura que el telefono y la PC esten en la misma red Wi-Fi o LAN.
3. Permite Apache en el firewall de Windows si la red bloquea el acceso.
4. Verifica que MySQL y Apache esten iniciados en XAMPP.

## Nota

Si el proyecto se mueve a otro puerto o a otra carpeta, no hace falta editar el codigo de negocio; basta con actualizar la configuracion local.
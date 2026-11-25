# Guia de presentacion universitaria

## Preparacion segura

> Control previo: si `127.0.0.1:3306` no acepta conexiones, no intentes migrar ni sembrar datos. Confirma primero que la instancia MySQL autorizada esta disponible y vuelve a ejecutar el preflight protegido. El control del 2026-11-18 encontro MySQL no disponible y no realizo cambios administrativos.

1. Confirma que MySQL2610 esta en ejecucion. La base aislada `importsystemgo_demo` y su cuenta local ya deben estar aprovisionadas.
2. Usa las credenciales DEMO que ya te fueron entregadas de forma segura. No ejecutes la rotacion de credenciales si siguen disponibles y vigentes.
3. Si necesitas rotarlas porque ya no tienes acceso, solicita autorizacion y ejecuta el procedimiento solo desde una terminal interactiva de VS Code. El comando invalida las contrasenas actuales y sesiones DEMO; no lo ejecutes desde una terminal automatizada.
4. No guardes contrasenas en el repositorio, capturas, documentos ni logs.
5. Ejecuta `npm run build` y, en otra terminal, `./scripts/with_demo_environment.ps1 -Action serve`.
6. Abre `http://127.0.0.1:8000/login`.

No es necesario escribir la contrasena MySQL en `.env`. El comando se niega a operar sin terminal interactiva, fuera de `local`, con la opcion DEMO desactivada, con otra base o con otra cuenta MySQL.

## Recorrido recomendado

1. **Administrador DEMO:** muestra el resumen organizativo, las siete sucursales, empleados, usuarios y roles.
2. **Jefe de operaciones La Paz:** demuestra que solo recibe ambito operativo sobre LP01-LP04.
3. **Jefe de operaciones El Alto:** demuestra el aislamiento de EA01-EA03 respecto de La Paz.
4. **Jefe de tienda LP01:** muestra su equipo y recursos de sucursal; la comparacion externa es solamente de lectura.
5. **Vendedor LP01:** muestra su espacio de ventas y productos. Explica como pendiente cualquier flujo que aun no tenga esquema operativo real.
6. **Cajero LP01:** muestra el espacio de caja y la separacion de funciones.
7. **Almacenero, creditos y compras:** muestra que la navegacion y autorizacion cambian por responsabilidad.

## Mensaje tecnico para la defensa

- Laravel 13, Blade, Bootstrap 5, Vite, MySQL y Spatie Permission con Teams.
- Roles locales ligados a sucursal y capacidades globales explicitas; no existe un `Gate::before` que ignore el ambito.
- La autorizacion territorial se comprueba en middleware, Policies y Services, no solo en la interfaz.
- Los datos DEMO viven en una base aislada, estan marcados y se crean idempotentemente.
- MySQL guarda hashes; las contrasenas en texto plano solo existen durante la salida interactiva que ve el expositor.

## Limites que deben declararse

No presentes botones o tarjetas pendientes como operaciones terminadas. El dashboard etiqueta expresamente los modulos sin tablas operativas. No hay facturacion fiscal real ni conexion al SIN. Los modulos de productos, kardex, ventas, caja, cobros y reportes se consideran completos solo cuando tengan migraciones aisladas, servicios, Policies y pruebas aprobadas.

## Evidencia

Antes de la exposicion conserva solo resultados sin secretos: salida de PHPUnit, Pint, compilacion Vite y conteos del entorno DEMO. Nunca incluyas la tabla de contrasenas en la evidencia.

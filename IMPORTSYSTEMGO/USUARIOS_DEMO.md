# Usuarios de demostracion

Estas identidades son exclusivamente academicas. Las contrasenas no se guardan en este documento, en el repositorio ni en archivos de configuracion. Se generan o renuevan de forma interactiva con `php artisan demo:credentials`, unicamente sobre la base aislada `importsystemgo_demo`.

| Cargo | Nombre ficticio | Correo | Sucursal / ambito | Funciones autorizadas |
|---|---|---|---|---|
| Administrador general DEMO | Adriana Demo | admin.demo@importsystemgo.example | Todas las sucursales DEMO | Administracion, configuracion y consulta consolidada |
| Jefe de operaciones | Oscar Quispe | operaciones.lp@importsystemgo.example | La Paz: LP01, LP02, LP03 y LP04 | Supervision territorial, indicadores y aprobaciones autorizadas |
| Jefe de operaciones | Elena Mamani | operaciones.ea@importsystemgo.example | El Alto: EA01, EA02 y EA03 | Supervision territorial, indicadores y aprobaciones autorizadas |
| Jefe de tienda | Julia Flores | jefe.lp01@importsystemgo.example | LP01 Centro | Equipo, inventario, caja e indicadores de su tienda |
| Vendedor | Valeria Rojas | vendedor.lp01.01@importsystemgo.example | LP01 Centro | Productos, ventas propias, cotizaciones y reservas |
| Cajero | Carlos Condori | cajero.lp01@importsystemgo.example | LP01 Centro | Apertura, cobros, arqueo y cierre de su caja |
| Almacenero | Andres Alarcon | almacen.lp01@importsystemgo.example | LP01 Centro | Existencias, recepciones, kardex y transferencias autorizadas |
| Asesor de creditos | Camila Vargas | creditos.lp01@importsystemgo.example | LP01 Centro | Clientes, solicitudes, cuotas y cobranzas autorizadas |
| Encargado de compras | Bruno Salazar | compras.lp01@importsystemgo.example | LP01 Centro | Proveedores, ordenes y recepciones autorizadas |

El catalogo completo agrega un jefe, tres vendedores y un cajero en cada una de las siete sucursales. En total se preparan 37 empleados ficticios y 41 cuentas DEMO: los tres especialistas y la administradora general son cuentas de presentacion separadas del maestro de empleados.

## Sucursales incluidas

- La Paz: LP01 Centro, LP02 Sopocachi, LP03 Miraflores y LP04 Calacoto.
- El Alto: EA01 Ceja, EA02 Satelite y EA03 Villa Adela.

La lista completa de correos se presenta una sola vez en la terminal cuando se ejecuta el comando de credenciales. Ninguna cuenta DEMO reutiliza o modifica la cuenta administrativa real.

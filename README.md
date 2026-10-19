# ClassTech

Aplicación web para la **reserva de salones y consulta de horarios** de la
Institución Educativa INEM José Félix de Restrepo (Medellín, Colombia).
Proyecto de grado.

## Integrantes
- [Integrante 1]
- [Integrante 2]
- [Integrante 3]

## Funcionalidades
- **Mapa interactivo** del campus con 8 bloques y sus salones.
- **Reservas** de salones por día y franja horaria (horarios A y B), sin cruces.
- **Mi Horario**: horario semanal de los 17 grupos de grado 11.
- **Roles**: administrador, docente y estudiante (los estudiantes no pueden reservar).
- **Panel administrativo**: búsqueda y eliminación de usuarios y reservas, y copia de seguridad en `.sql`.
- **Seguridad**: contraseñas con bcrypt, consultas preparadas (PDO), protección CSRF,
  recuperación de contraseña por correo (token SHA-256, vence en 30 min) y
  verificación en dos pasos TOTP (RFC 6238).

## Tecnologías
- PHP (sin framework) + MySQL con PDO
- HTML, JavaScript y Materialize CSS
- Brevo (API) para correos transaccionales
- Hosting: InfinityFree

## Instalación
1. Crea una base de datos MySQL e importa `schema_infinityfree.sql`.
2. Ejecuta, en este orden, `alter_usuarios_reset_token.sql` y `alter_usuarios_totp.sql`.
3. Copia `db_credentials.example.php` a `db_credentials.php` y completa tus datos.
4. Copia `email_credentials.example.php` a `email_credentials.php` y pon tu API key de Brevo.
5. Sube los archivos a tu hosting (o usa un servidor local con PHP 8+).
6. Agrega las imágenes `INEMlogo.png` e `Inem_dron.jpg` en la raíz del sitio.
7. Para crear un administrador, regístrate y luego ejecuta en MySQL:
   ```sql
   UPDATE usuarios SET rol = 'administrador' WHERE correo = 'tu_correo@inemjose.edu.co';
   ```

## Estructura
| Archivo | Descripción |
|---|---|
| `index.html` | Mapa interactivo del campus |
| `TABLA.html`, `reservas.php` | Consulta y reserva de salones |
| `horario.html`, `api_horario.php` | Horarios de grado 11 |
| `login.php`, `registro.php`, `logout.php` | Autenticación |
| `olvide_password.php`, `restablecer_password.php` | Recuperación de contraseña |
| `totp_*.php`, `login_verificar_totp.php` | Verificación en dos pasos |
| `admin*.php`, `backup.php` | Panel administrativo |

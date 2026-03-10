# Reloj Checador - Angular + Laravel

Sistema de asistencia móvil con validación de geolocalización.

## Requisitos
- PHP 8.2+ / Laravel 11
- Node.js & npm
- Ionic CLI
- MySQL (o SQLite para pruebas rápidas)

## Configuración Backend (Laravel 11)
1. Entra a la carpeta `backend`.
2. Ejecuta `composer install`.
3. Configura tu `.env` (DB_DATABASE, DB_USERNAME, DB_PASSWORD).
4. Ejecuta las migraciones y seeders:
   ```bash
   php artisan migrate --seed
   ```
5. Inicia el servidor:
   ```bash
   php artisan serve
   ```

### Estructura de Base de Datos (MySQL)
- `users`: Usuarios del sistema.
- `schedules`: Horarios asignados por día de la semana.
- `attendance`: Registro de entradas, salidas, coordenadas y distancia.
- `company_settings`: Almacena `latitude` y `longitude` del centro de trabajo.

## Configuración Frontend (Ionic/Angular)
1. Entra a la carpeta `frontend`.
2. Ejecuta `npm install`.
3. Asegúrate de que el `apiUrl` en `src/environments/environment.ts` apunte a tu backend.
4. Ejecuta en modo navegador o móvil:
   ```bash
   ionic serve
   ```

## Gestión de Horarios (Ejemplos)
Para asignar un horario a un usuario, puedes insertar registros en la tabla `schedules`.

**Ejemplo SQL:**
```sql
INSERT INTO schedules (user_id, day_of_week, start_time, end_time)
VALUES (1, 'monday', '08:00:00', '17:00:00');
```

**Ejemplo vía Eloquent (Laravel):**
```php
Schedule::create([
    'user_id' => $userId,
    'day_of_week' => 'monday',
    'start_time' => '08:00:00',
    'end_time' => '17:00:00',
]);
```

## Validación de Geocerca (100 metros)
La validación se realiza tanto en el frontend (Capacitor Geolocation) como en el backend (Laravel) usando la fórmula de Haversine para calcular la distancia entre la ubicación actual del usuario y el centro de trabajo guardado en `company_settings`.

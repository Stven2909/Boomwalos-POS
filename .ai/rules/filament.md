---
paths:
  - 'app/Providers/Filament/**'
---

# Filament

## No databaseNotifications(): la tabla notifications no existe
No llamar a ->databaseNotifications() en AdminPanelProvider: consulta la tabla `notifications`, que no existe en boomwalos_pos (falta migración) y rompe la página. La campana de notificaciones se muestra como icono estático en resources/views/filament/admin/components/topbar-actions.blade.php.

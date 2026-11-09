import Alpine from 'alpinejs';
import notificationBell from './notifications';

window.Alpine = Alpine;

Alpine.data('notificationBell', notificationBell);

Alpine.start();
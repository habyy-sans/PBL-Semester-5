import Alpine from 'alpinejs';
import notificationBell from './notifications';
import './scroll-autohide';

window.Alpine = Alpine;

Alpine.data('notificationBell', notificationBell);

Alpine.start();
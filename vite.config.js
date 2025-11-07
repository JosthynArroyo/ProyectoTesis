// vite.config.js
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
  
  plugins: [
    laravel({
      input: [
        // ==== CSS globales / panel ====
        'resources/css/app.css',
        'resources/css/login.css',
        'resources/css/register.css',
        'resources/css/welcome.css',

        // ==== CSS de dashboards ====
        'resources/css/dashboards/admin.css',
        'resources/css/dashboards/citas.css',
        'resources/css/dashboards/doctor.css',
        

        // ==== CSS Paciente ====
        'resources/css/paciente/citas.css',
        'resources/css/paciente/crear-cita.css',
        'resources/css/paciente/paciente.css', 
        'resources/css/paciente/editar-cita.css',
        'resources/css/paciente/perfil.css',
        // ==== JS Paciente ====
        'resources/js/paciente/crear-cita.js',
        'resources/js/paciente/dashboard-paciente.js',
        'resources/js/paciente/citas.js',
        'resources/js/paciente/perfil.js',

        // ==== CSS Doctor ====
        // ==== JS Doctor ====


        // ==== CSS Administrador ====
        // ==== JS Administrador ====





        // ==== JS globales / utilitarios ====
        'resources/js/app.js',
        'resources/js/bootstrap.js',
        'resources/js/sidebar-toggle.js',

        // ==== JS de vistas ====
        
        'resources/js/dashboard-admin.js',
        'resources/js/dashboard-admin-extras.js',
        'resources/js/dashboard-doctor.js',
        
        'resources/js/welcome-login-modal.js',

        
      ],
      refresh: true,
    }),
  ],
})

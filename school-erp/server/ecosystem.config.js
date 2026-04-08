module.exports = {
  apps: [
    {
      name: 'school-erp-server',
      script: 'server.js',
      instances: 'max', // Auto-scale to CPU cores
      exec_mode: 'cluster',
      max_memory_restart: '1G',
      env: {
        NODE_ENV: 'production',
        PORT: 5000
      },
      // Advanced settings
      max_restarts: 10,
      restart_delay: 4000,
      wait_ready: true,
      listen_timeout: 10000,
      kill_timeout: 5000,
      // Logging
      log_date_format: 'YYYY-MM-DD HH:mm:ss',
      error_file: './logs/pm2-error.log',
      out_file: './logs/pm2-out.log',
      merge_logs: true,
      // Health check
      wait_ready: true
    }
  ]
};

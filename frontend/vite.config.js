import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// 本地开发：5173 页面，API 与照片代理到 FrankenPHP 上的 Laravel (8000)
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': 'http://127.0.0.1:8000',
      '/storage': 'http://127.0.0.1:8000',
    },
  },
})

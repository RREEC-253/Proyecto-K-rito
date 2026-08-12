import axios from 'axios'

const api = axios.create({
  baseURL: 'http://localhost:8080/api/v1', // Apunta al puerto 8080 de Nginx
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

export default api
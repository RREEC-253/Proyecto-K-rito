import axios from 'axios'

const api = axios.create({
  baseURL: 'http://localhost:8080/api/v1', // Apunta al puerto 8080 de Nginx
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('access_token')
  if(token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
},
(error) => {
  return Promise.reject(error)
 }
)   
export default api
# Arquitectura de Alta Disponibilidad, Balanceo y Tríada CIA

Arquitectura contenerizada con Docker Compose que implementa balanceo de carga web (HAProxy + NGINX) y persistencia de datos (MySQL Primary-Replica) aplicando los principios de la tríada de seguridad CIA.

## 🏗 Arquitectura de Red y Servicios

| Servicio | Contenedor | IP Asignada | Función |
|---|---|---|---|
| **HAProxy** | `lb_haproxy` | `192.168.100.10` | Balanceador de carga Round-Robin (HTTP :80 / Stats :8404) |
| **Web 1** | `web1` | `192.168.100.11` | Servidor Web NGINX (Nodo A) |
| **Web 2** | `web2` | `192.168.100.12` | Servidor Web NGINX (Nodo B) |
| **DB Master** | `mysql_primary` | `192.168.100.20` | Base de datos principal (Lectura y Escritura) |
| **DB Replica** | `mysql_replica` | `192.168.100.21` | Réplica de respaldo en modo `read-only` |

---

## 🛡 Implementación de la Tríada CIA

1. **Confidencialidad (C):** 
   * Principio de Mínimo Privilegio aplicado con el usuario `cliente_web`, quien solo tiene permisos de lectura (`SELECT`) en catálogos públicos (`catalogo_muebles`, `catalogo_zapateria`).
   * Acceso denegado a tablas sensibles (`usuarios`, `pasarela_pagos`).
2. **Integridad (I):** 
   * Modo `super_read_only = ON` en el nodo réplica para evitar corrupción o alteraciones directas fuera del nodo maestro.
3. **Disponibilidad (A):** 
   * Balanceo de peticiones y tolerancia a fallos con HAProxy ante la caída intencional de nodos web.

---

## 🚀 Despliegue Rápido

```bash
sudo docker compose up -d

# SIGIB - Sistema de Gestión Integral para Abasto Blanco

## Versión 2.0 - Prototipo Mejorado

### 📋 Descripción
Sistema de gestión integral para abastos comerciales, enfocado en el control de inventario, ventas, clientes y cobranza, con especial énfasis en el manejo de envases retornables.

### 🚀 Características Principales

#### 1. **Dashboard Inteligente**
- KPIs en tiempo real
- Gráficos interactivos
- Alertas automáticas
- Últimas transacciones

#### 2. **Gestión de Clientes**
- Perfil 360° del cliente
- Historial completo
- Sistema de clasificación
- Límites de crédito

#### 3. **Control de Inventario**
- Alertas de stock bajo
- Ajustes de inventario
- Movimientos históricos
- Valorización del inventario

#### 4. **Módulo de Ventas**
- Carrito de compras
- Validación en tiempo real
- Control de vacíos retornables
- Comprobantes profesionales

#### 5. **Sistema de Cobranza**
- Seguimiento de deudas
- Recordatorios automáticos
- Registro de abonos
- Reportes de morosidad

### 🛠️ Instalación

#### Requisitos
- PHP 7.4 o superior
- MySQL 5.7 o superior
- Apache o Nginx
- Extensiones PHP: PDO, JSON, GD

#### Pasos de Instalación

1. **Configurar Base de Datos:**
```bash
mysql -u root -p < sigib_db.sql
mysql -u root -p < crear_tablas_adicionales.sql
# Recapet Wallet Challenge

A professional **financial wallet API** system built with **Laravel 12** that provides secure, scalable, and audit-friendly wallet services for real-world financial applications. The system handles user registration, wallet management, peer-to-peer transfers with sophisticated fee calculations, and comprehensive transaction tracking with immutable audit trails.

## 🎯 Overview

This wallet service is designed to meet enterprise-grade financial requirements with features including **idempotent transactions**, **concurrency-safe operations**, **immutable ledger entries**, and **automated balance reconciliation**. Built following **SOLID principles** and **clean architecture patterns** for maximum reliability and maintainability.

### Key Financial Features
- **Secure P2P Transfers**: Real-time money transfers between users with automatic fee calculation
- **Fee Structure**: Smart fee calculation ($2.50 + 10% for transfers > $25)
- **Idempotent Operations**: Duplicate transaction prevention using unique request identifiers
- **Immutable Audit Trail**: Complete transaction history with balance snapshots for reconciliation
- **Concurrency Safety**: Row-level locking prevents double-spending and race conditions

## ✨ Core Features

### 💰 Wallet Management
- **Automatic Wallet Creation**: Every user receives exactly one wallet upon registration
- **Balance Inquiry**: Real-time balance checking with transaction history
- **Multi-Operation Support**: Deposits, withdrawals, and peer-to-peer transfers
- **Status Management**: Active/inactive wallet states with comprehensive validation

### 🔄 Transaction Processing
- **Deposit Operations**: Secure fund additions with permanent transaction records
- **Withdrawal Operations**: Balance-validated withdrawals with status tracking
- **P2P Transfers**: Inter-user transfers with sophisticated fee calculations
- **Transaction History**: Paginated history with advanced filtering and search

### 🔒 Security & Compliance
- **Token-Based Authentication**: Laravel Sanctum for secure API access
- **Request Validation**: Comprehensive input validation with custom error handling
- **Activity Logging**: Detailed audit trails for all user actions and system events
- **Rate Limiting**: Configurable rate limits on authentication and transaction endpoints

### 📊 Monitoring & Analytics
- **Balance Snapshots**: Automated daily balance recording for historical analysis
- **Health Monitoring**: Comprehensive system health checks with performance metrics
- **Transaction Analytics**: Statistical summaries and financial reporting
- **Audit Reconciliation**: Automated balance verification against transaction history

## 📋 Technical Requirements

- **PHP**: 8.2 or higher
- **Laravel**: 12.x
- **Database**: MySQL 8.0+

## 🚀 Quick Start

### 1. Clone & Install
```bash
# Clone the repository
git clone https://github.com/Maahmoudd/recapet-wallet-challenge.git
cd recapet-wallet-challenge

# Install dependencies
composer install
```

### 2. Environment Setup
```bash
# Copy environment configuration
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database in .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=recapet_wallet
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 3. Database Initialization
```bash
# Run migrations
php artisan migrate

# Seed with sample data (optional)
php artisan db:seed

# Or fresh install with seeds
php artisan migrate:fresh --seed
```

### 4. Launch Application
```bash
# Start development server
php artisan serve

# API available at: http://localhost:8000/api
```

### 5. Schedule Setup (Production)
```bash
php artisan schedule:run
```

## 📚 API Documentation

### Base URL
```
http://localhost:8000/api
```

### Authentication
All protected endpoints require a Bearer token obtained through login.

```http
Authorization: Bearer token
```

### Core Endpoints

#### 🔐 Authentication
```http
POST   /auth/register        # User registration with automatic wallet creation
POST   /auth/login           # User authentication
POST   /auth/logout          # Secure logout
GET    /auth/me              # Get authenticated user profile
```

#### 💳 Wallet Operations
```http
POST   /wallet/deposit       # Add funds to wallet
POST   /wallet/withdraw      # Withdraw funds from wallet
POST   /wallet/transfer      # P2P money transfer
GET    /wallet/transactions  # Transaction history with filters
```

#### 📊 Monitoring
```http
GET    /healthz              # System health check
GET    /health/ready         # Readiness probe
GET    /health/live          # Liveness probe
```

### Sample API Requests

#### User Registration
```json
POST /api/auth/register
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "SecurePassword123",
}
```

#### Wallet Deposit
```json
POST /api/wallet/deposit
{
    "amount": 500.00,
    "idempotency_key": "dep_20250115_001"
}
```

#### P2P Transfer
```json
POST /api/wallet/transfer
{
    "recipient_email": "jane@example.com",
    "amount": 100.00,
    "idempotency_key": "txn_20250115_001"
}
```

#### Transaction History
```http
GET /api/wallet/transactions?type=transfer&status=completed&per_page=25&page=1
```

### Response Format
All API responses follow a consistent structure:

#### Success Response
```json
{
    "success": true,
    "message": "Operation completed successfully",
    "data": {
        "transaction": { ... },
        "new_balance": "1,450.75"
    }
}
```

#### Error Response
```json
{
    "success": false,
    "message": "Insufficient balance for withdrawal",
    "data": {
        "error_code": "INSUFFICIENT_BALANCE"
    }
}
```

## 🗄️ Database Architecture

### Core Tables

#### Users & Authentication
- **users**: User profiles and authentication data
- **personal_access_tokens**: API authentication tokens

#### Wallet System
- **wallets**: Wallet records with balance and status
- **transactions**: Immutable transaction records with metadata
- **balance_snapshots**: Daily balance recordings for audit

#### Audit & Monitoring
- **activity_logs**: Comprehensive user and system activity tracking
- **ledger_entries**: Double-entry accounting records (if implemented)

### Key Relationships
```
User (1) ──→ (1) Wallet
Wallet (1) ──→ (∞) Transactions
Wallet (1) ──→ (∞) BalanceSnapshots
User (1) ──→ (∞) ActivityLogs
```

## 🏗️ Architecture & Design

### Design Patterns
- **Repository Pattern**: Clean data access abstraction
- **Action Pattern**: Encapsulated business logic operations
- **Service Layer**: Complex business rule management
- **Observer Pattern**: Automated logging and notifications

### Code Organization
```
app/
├── Actions/
│   ├── Auth/                 # Authentication operations
│   ├── Wallet/               # Wallet business logic
│   └── System/               # System operations
├── Http/
│   ├── Controllers/Api/      # API controllers
│   ├── Requests/             # Form request validation
│   └── Resources/            # API response formatting
├── Models/                   # Eloquent models
├── Repositories/
│   ├── Contract/             # Repository interfaces
│   └── Eloquent/             # Eloquent implementations
└── Console/Commands/         # Artisan commands

database/
├── migrations/               # Database schema
├── seeders/                  # Sample data
└── factories/                # Model factories

tests/
├── Feature/                  # API integration tests
└── Unit/                     # Unit tests
```

### Financial Calculation Logic

#### Transfer Fee Calculation
```php
// Fee structure: $2.50 + 10% for amounts > $25
if ($amount <= 25.00) {
    return 0;
}

$baseFee = 2.50;
$percentageFee = $amount * 0.10;
return round($baseFee + $percentageFee, 2);
```

#### Concurrency Safety
```php
// Row-level locking prevents race conditions
$senderWallet = $walletRepository->findByUserIdForUpdate($userId);
$recipientWallet = $walletRepository->findByUserIdForUpdate($recipientId);

// Atomic balance updates within database transaction
DB::transaction(function() {
    // Validate balances
    // Create transaction records
    // Update wallet balances
    // Log activities
});
```

## 🧪 Testing

### Test Coverage
The application includes comprehensive test coverage:

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

```

### Test Categories
- ✅ **Authentication Flow**: Registration, login, logout, profile access
- ✅ **Wallet Operations**: Deposits, withdrawals, transfers, balance inquiry
- ✅ **Transaction History**: Filtering, pagination, search functionality
- ✅ **Fee Calculations**: Various transfer amounts and fee scenarios
- ✅ **Concurrency Testing**: Multiple simultaneous operations
- ✅ **Error Handling**: Invalid inputs, insufficient funds, duplicate transactions
- ✅ **Security Testing**: Authentication, authorization, input validation

## ⚡ Performance & Scalability

### Database Optimization
- **Indexing Strategy**: Optimized indexes on frequently queried columns
- **Connection Pooling**: Efficient database connection management
- **Query Optimization**: Eager loading and efficient query patterns
- **Pagination**: Memory-efficient data retrieval

### Caching Implementation
```php
// Balance caching for frequently accessed data
Cache::remember("wallet_balance_{$walletId}", 300, function() {
    return $this->walletRepository->getBalance($walletId);
});
```

### Concurrent Operation Safety
- **Row-Level Locking**: Prevents double-spending scenarios
- **Atomic Transactions**: Database-level consistency guarantees
- **Idempotency Keys**: Duplicate operation prevention
- **Optimistic Locking**: Version-based conflict resolution

## 🔒 Security Features

### Authentication & Authorization
- **Laravel Sanctum**: Token-based API authentication
- **Password Hashing**: Bcrypt with salt for secure password storage
- **Rate Limiting**: Configurable limits on authentication attempts
- **CORS Protection**: Cross-origin request security

### Transaction Security
- **Input Validation**: Comprehensive request validation with custom rules
- **SQL Injection Prevention**: Parameterized queries through Eloquent ORM
- **XSS Protection**: Output sanitization and encoding
- **CSRF Protection**: Token-based request verification

### Audit & Compliance
- **Activity Logging**: Complete audit trail for all operations
- **Immutable Records**: Transaction data cannot be modified
- **Balance Reconciliation**: Automated verification against ledger
- **Data Retention**: Configurable retention policies for compliance

## 📊 Monitoring & Observability

### Health Checks
The system provides comprehensive health monitoring:

#### System Health (`/healthz`)
```json
{
    "status": "healthy",
    "services": {
        "database": { "status": "healthy", "response_time_ms": 12.5 },
        "cache": { "status": "healthy", "response_time_ms": 3.2 },
        "wallet_service": { "status": "healthy", "active_wallets": 1250 },
        "transaction_service": { "status": "healthy", "pending_transactions": 5 }
    },
    "system_info": {
        "memory_usage": "45.2 MB",
        "uptime": "5 days"
    }
}
```

### Automated Tasks
- **Daily Balance Snapshots**: Automated at 11:59 PM daily
- **Health Monitoring**: Hourly system health checks
- **Transaction Monitoring**: 15-minute checks for stuck transactions
- **Log Cleanup**: Automated old log removal

## 🚀 Production Deployment

### Environment Configuration
```bash
# Production environment setup
APP_ENV=production
APP_DEBUG=false
APP_URL=https://recapet-wallet.com

# Database configuration
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_DATABASE=recapet_wallet_prod

# Cache configuration
CACHE_DRIVER=redis
REDIS_HOST=your-redis-host

# Queue configuration
QUEUE_CONNECTION=redis
```

### Performance Optimization
```bash
# Cache configuration for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimize autoloader
composer install --optimize-autoloader --no-dev

# Generate optimized class map
php artisan optimize
```

## 📖 Advanced Configuration

### Wallet System Settings
```php
// config/wallet.php
'calculation' => [
    'min_transfer_amount' => 25.00,
    'base_fee' => 2.50,
    'percentage_fee' => 0.10,
],

'limits' => [
    'max_transaction_amount' => 999999.99,
    'min_transaction_amount' => 0.01,
    'daily_transfer_limit' => 50000.00,
],

'snapshots' => [
    'retention_days' => 365,
    'schedule_time' => '23:59',
],
```

## 🧰 Development Tools

### Useful Artisan Commands
```bash
# Create balance snapshots manually
php artisan wallet:create-snapshots

# Create snapshots for specific date
php artisan wallet:create-snapshots --date=2025-01-15

# Force recreate existing snapshots
php artisan wallet:create-snapshots --force

# Clear application caches
php artisan optimize:clear
```

### Database Seeding
```bash
php artisan db:seed
```

## 📖 Additional Documentation

- **[API Documentation](https://documenter.getpostman.com/view/40385378/2sB34hHg2U)**: Complete API reference
- **[Database Schema](https://dbdiagram.io/d/6875657cf413ba3508d4f3a9)**: Detailed ERD and table structures
- **[Testing Guide](https://pestphp.com/docs/installation)**: Comprehensive testing documentation
- **[Deployment Guide](https://cloud.laravel.com/)**: Production deployment instructions

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Guidelines
- Follow PSR-12 coding standards
- Write comprehensive tests for new features
- Update documentation for API changes
- Ensure all tests pass before submitting PR

## 📞 Support

For support and questions:
- **Email**: mahmoudmuhammed2610@gmail.com

---

**Built with ❤️ using Laravel 12, MySQL, and modern PHP practices.**

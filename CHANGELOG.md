# Changelog

All notable changes to `cashkdiopen/laravel` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2025-09-01

### Added
- Initial release of Cashkdiopen Laravel package
- Support for Orange Money payment integration
- Support for MTN Mobile Money payment integration  
- Support for Bank Cards payment integration
- Unified API for all payment providers
- Laravel ServiceProvider with auto-discovery
- Comprehensive configuration system
- Secure webhook handling with HMAC signature validation
- API key authentication and authorization system
- Rate limiting per API key
- Complete Eloquent models for transactions, payments, API keys, and webhook logs
- RESTful API endpoints for payment management
- Request validation with custom form requests
- Comprehensive middleware stack (authentication, rate limiting, request logging)
- Database migrations with optimized indexes
- Comprehensive test suite (Unit, Feature, Integration)
- Complete documentation (PRD, Architecture, API Reference)
- Laravel Facade for easy usage
- Artisan commands for API key management and cleanup
- Event system for payment lifecycle
- Queue jobs for asynchronous webhook processing
- Caching for improved performance
- Comprehensive logging with sanitization
- Multi-environment support (sandbox/production)
- Scoped API access control
- Automatic transaction expiry handling
- Idempotent operations to prevent duplicates
- Phone number validation for mobile money providers
- Currency and amount validation per provider
- Soft deletes for data retention
- Pagination and filtering for API endpoints
- Health check endpoints
- Request ID tracking for debugging
- Performance monitoring and slow query detection
- Security features (HTTPS enforcement, IP restrictions)

### Security
- HMAC SHA-256 signature validation for webhooks
- Encrypted storage of API secrets
- Rate limiting to prevent abuse
- Input validation and sanitization
- SQL injection protection
- CSRF protection (with webhook exemptions)
- Sensitive data masking in logs
- Secure API key generation and rotation
- Timestamp validation for replay attack prevention

### Documentation
- Complete PRD (Product Requirements Document)
- Technical architecture documentation
- Comprehensive API documentation with examples
- Installation and configuration guides
- Security best practices
- Testing documentation
- Contribution guidelines

### Testing
- Unit tests for all models and services
- Feature tests for API endpoints
- Integration tests with external providers (mocked)
- Test fixtures and factories
- Coverage > 90% for critical code paths

[Unreleased]: https://github.com/cashkdiopen/laravel/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/cashkdiopen/laravel/releases/tag/v1.0.0
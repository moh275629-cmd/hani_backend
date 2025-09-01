# Hani Backend - New Features Implementation

This document outlines all the new features that have been implemented in the Hani backend system.

## 🆕 **New Features Overview**

### 1. **Client Approval System**
- **Purpose**: Clients now require admin approval before they can login
- **Implementation**: Added `is_approved` field to users table
- **Logic**: Separate from store approval (as requested)
- **Email**: Notification sent when client is approved

### 2. **Reports System**
- **Purpose**: Users can report stores/other users for admin review
- **Features**:
  - Create reports with descriptions
  - Admin can review and take actions
  - Actions: let go, warning, close account
  - Email notifications for all actions

### 3. **Account Expiration System**
- **Purpose**: Automatic deactivation after one year of activation
- **Features**:
  - Tracks approval and deactivation dates
  - Automatic deactivation via scheduled command
  - Admin can extend, reactivate, or deactivate accounts
  - Email notifications for expiration

### 4. **Enhanced Wilaya Filtering**
- **Purpose**: Fixed wilaya-based filtering for regional admins
- **Features**:
  - Regional admins only see data from their wilaya
  - Applies to users, stores, reports, and expired accounts
  - Global admins can see all data

## 🗄️ **Database Changes**

### New Tables Created

#### 1. **Reports Table**
```sql
CREATE TABLE `reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reporter_id` bigint(20) UNSIGNED NOT NULL,
  `reported_user_id` bigint(20) UNSIGNED NOT NULL,
  `description` text NOT NULL,
  `status` enum('pending','under_review','resolved','dismissed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
);
```

#### 2. **Activations Table**
```sql
CREATE TABLE `activations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `approved_at` timestamp NULL,
  `deactivate_at` timestamp NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL
);
```

### Modified Tables

#### 1. **Users Table**
- Added `is_approved` boolean field (default: false)

## 🔧 **New API Endpoints**

### Reports Management
- `POST /api/reports` - Create a new report
- `GET /api/reports` - Get user's own reports
- `GET /api/admin/reports` - Admin view reports (filtered by wilaya)
- `PUT /api/admin/reports/{id}/status` - Update report status and take action

### Expired Accounts Management
- `GET /api/admin/expired-accounts` - View expired accounts
- `GET /api/admin/expiring-soon` - View accounts expiring soon
- `POST /api/admin/expired-accounts/{userId}/reactivate` - Reactivate account
- `POST /api/admin/expired-accounts/{userId}/extend` - Extend activation
- `POST /api/admin/expired-accounts/{userId}/deactivate` - Deactivate account

### Client Approval
- `POST /api/admin/clients/{userId}/approve` - Approve client account

## 📧 **Email Templates Created**

### 1. **Client Approved Email** (`emails/client-approved.blade.php`)
- Sent when admin approves a client account
- Green theme with welcome message
- Includes login button

### 2. **Account Expired Email** (`emails/account-expired.blade.php`)
- Sent when account expires
- Red theme with warning message
- Instructions for reactivation

### 3. **Report Action Email** (`emails/report-action.blade.php`)
- Sent when admin takes action on a report
- Orange theme with action details
- Different content based on action type

## 🚀 **New Controllers**

### 1. **ReportController**
- Handles report creation and management
- Admin actions on reports
- Email notifications

### 2. **ActivationController**
- Manages account activation lifecycle
- Handles expired accounts
- Extension and reactivation logic

## ⚙️ **New Models**

### 1. **Report Model**
- Manages user reports
- Status management (pending, under_review, resolved, dismissed)
- Relationships with reporter and reported user

### 2. **Activation Model**
- Tracks user approval and deactivation dates
- Scopes for expired, active, and expiring accounts
- Methods for extending and managing activation

## 🔄 **Updated Models**

### 1. **User Model**
- Added `is_approved` field
- New approval methods (approve, reject, deactivate, reactivate)
- Relationships with reports and activations
- Enhanced scopes for filtering

### 2. **Store Model**
- Updated to sync with user approval status
- Automatic user approval when store is approved

## 📋 **New Artisan Commands**

### 1. **DeactivateExpiredAccounts**
- **Command**: `php artisan accounts:deactivate-expired`
- **Purpose**: Automatically deactivate expired accounts
- **Features**: Sends email notifications, handles store deactivation
- **Usage**: Can be scheduled to run daily

## 🔐 **Security & Access Control**

### Role-Based Access
- **Clients**: Can create reports, view their own reports
- **Regional Admins**: Can manage data from their wilaya only
- **Global Admins**: Can manage all data across all wilayas

### Wilaya Filtering
- All admin endpoints respect wilaya restrictions
- Regional admins cannot access data from other wilayas
- Global admins can filter by wilaya if needed

## 📅 **Scheduling Recommendations**

### Daily Tasks
```bash
# Add to your crontab or scheduler
0 2 * * * cd /path/to/hani_backend && php artisan accounts:deactivate-expired
```

### Weekly Tasks
- Review expired accounts
- Check accounts expiring soon
- Review pending reports

## 🧪 **Testing the New Features**

### 1. **Test Client Registration & Approval**
```bash
# Register a new client
POST /api/register
{
  "name": "Test Client",
  "email": "test@example.com",
  "phone": "123456789",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "client"
}

# Admin approves the client
POST /api/admin/clients/{userId}/approve
```

### 2. **Test Report Creation**
```bash
# Create a report
POST /api/reports
{
  "reported_user_id": 123,
  "description": "This user is violating terms of service"
}
```

### 3. **Test Account Expiration**
```bash
# Run the expiration command
php artisan accounts:deactivate-expired
```

## 🚨 **Important Notes**

### 1. **Email Configuration**
- Ensure your Laravel email configuration is properly set up
- Test email sending before deploying to production
- Consider using queue jobs for email sending in production

### 2. **Database Backups**
- Always backup your database before running migrations
- Test migrations in a development environment first

### 3. **Admin Permissions**
- Ensure admin users have the correct role assignments
- Test wilaya filtering with different admin accounts

### 4. **Scheduled Commands**
- Set up proper cron jobs or task schedulers
- Monitor command execution logs
- Consider using Laravel's task scheduler

## 🔍 **Troubleshooting**

### Common Issues

#### 1. **Migration Errors**
- Check database connection
- Ensure all required tables exist
- Verify foreign key constraints

#### 2. **Email Not Sending**
- Check Laravel mail configuration
- Verify SMTP credentials
- Check application logs for errors

#### 3. **Wilaya Filtering Not Working**
- Verify admin user has correct state/wilaya
- Check encryption/decryption of state fields
- Ensure proper role assignments

## 📚 **Additional Resources**

### Laravel Documentation
- [Mail Configuration](https://laravel.com/docs/mail)
- [Task Scheduling](https://laravel.com/docs/scheduling)
- [Database Migrations](https://laravel.com/docs/migrations)

### Hani Documentation
- Check existing documentation for API patterns
- Review existing email templates for consistency
- Follow established coding standards

---

**Implementation Date**: January 2025  
**Version**: 1.0  
**Status**: Complete and Ready for Testing

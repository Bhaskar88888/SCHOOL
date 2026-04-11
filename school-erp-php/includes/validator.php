<?php
/**
 * Input Validation Helper
 * School ERP PHP v3.0
 */

class Validator {
    
    private static $errors = [];
    
    /**
     * Validate required fields
     */
    public static function required($data, $fields) {
        self::reset();
        foreach ($fields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                self::$errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        return empty(self::$errors);
    }
    
    /**
     * Validate email format
     */
    public static function email($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::$errors['email'] = 'Invalid email format';
            return false;
        }
        return true;
    }
    
    /**
     * Validate minimum length
     */
    public static function minLength($value, $min, $field = 'value') {
        if (strlen($value) < $min) {
            self::$errors[$field] = ucfirst($field) . " must be at least $min characters";
            return false;
        }
        return true;
    }
    
    /**
     * Validate maximum length
     */
    public static function maxLength($value, $max, $field = 'value') {
        if (strlen($value) > $max) {
            self::$errors[$field] = ucfirst($field) . " must not exceed $max characters";
            return false;
        }
        return true;
    }
    
    /**
     * Validate numeric value
     */
    public static function numeric($value, $field = 'value') {
        if (!is_numeric($value)) {
            self::$errors[$field] = ucfirst($field) . ' must be a number';
            return false;
        }
        return true;
    }
    
    /**
     * Validate integer range
     */
    public static function range($value, $min, $max, $field = 'value') {
        if (!is_numeric($value) || $value < $min || $value > $max) {
            self::$errors[$field] = ucfirst($field) . " must be between $min and $max";
            return false;
        }
        return true;
    }
    
    /**
     * Validate date format
     */
    public static function date($date, $field = 'date') {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            self::$errors[$field] = ucfirst($field) . ' must be in YYYY-MM-DD format';
            return false;
        }
        return true;
    }
    
    /**
     * Validate allowed values
     */
    public static function in($value, $allowed, $field = 'value') {
        if (!in_array($value, $allowed)) {
            $allowedStr = implode(', ', $allowed);
            self::$errors[$field] = ucfirst($field) . " must be one of: $allowedStr";
            return false;
        }
        return true;
    }
    
    /**
     * Validate phone number
     */
    public static function phone($phone, $field = 'phone') {
        if (!preg_match('/^[+]?[\d\s\-()]{10,20}$/', $phone)) {
            self::$errors[$field] = 'Invalid phone number format';
            return false;
        }
        return true;
    }
    
    /**
     * Validate password strength
     */
    public static function password($password) {
        if (strlen($password) < 8) {
            self::$errors['password'] = 'Password must be at least 8 characters';
            return false;
        }
        if (!preg_match('/[A-Z]/', $password)) {
            self::$errors['password'] = 'Password must contain at least one uppercase letter';
            return false;
        }
        if (!preg_match('/[a-z]/', $password)) {
            self::$errors['password'] = 'Password must contain at least one lowercase letter';
            return false;
        }
        if (!preg_match('/[0-9]/', $password)) {
            self::$errors['password'] = 'Password must contain at least one number';
            return false;
        }
        return true;
    }
    
    /**
     * Sanitize string output
     */
    public static function sanitize($value) {
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Get all validation errors
     */
    public static function errors() {
        return self::$errors;
    }
    
    /**
     * Check if has errors
     */
    public static function hasErrors() {
        return !empty(self::$errors);
    }
    
    /**
     * Reset errors
     */
    public static function reset() {
        self::$errors = [];
    }
}

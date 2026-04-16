<?php
/**
 * Input Validation Helper
 * School ERP PHP v3.0
 *
 * Instance-based to avoid static state race conditions in concurrent requests.
 * Static convenience methods are provided for backward compatibility.
 */

class Validator
{
    private $errors = [];

    /**
     * Create a new Validator instance
     */
    public function __construct()
    {
        $this->errors = [];
    }

    /**
     * Factory method for fluent usage
     */
    public static function make()
    {
        return new self();
    }

    /**
     * Validate required fields
     */
    public function validateRequired($data, $fields)
    {
        foreach ($fields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $this->errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        return empty($this->errors);
    }

    /**
     * Validate email format
     */
    public function validateEmail($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errors['email'] = 'Invalid email format';
            return false;
        }
        return true;
    }

    /**
     * Validate minimum length
     */
    public function validateMinLength($value, $min, $field = 'value')
    {
        if (strlen($value) < $min) {
            $this->errors[$field] = ucfirst($field) . " must be at least $min characters";
            return false;
        }
        return true;
    }

    /**
     * Validate maximum length
     */
    public function validateMaxLength($value, $max, $field = 'value')
    {
        if (strlen($value) > $max) {
            $this->errors[$field] = ucfirst($field) . " must not exceed $max characters";
            return false;
        }
        return true;
    }

    /**
     * Validate numeric value
     */
    public function validateNumeric($value, $field = 'value')
    {
        if (!is_numeric($value)) {
            $this->errors[$field] = ucfirst($field) . ' must be a number';
            return false;
        }
        return true;
    }

    /**
     * Validate integer range
     */
    public function validateRange($value, $min, $max, $field = 'value')
    {
        if (!is_numeric($value) || $value < $min || $value > $max) {
            $this->errors[$field] = ucfirst($field) . " must be between $min and $max";
            return false;
        }
        return true;
    }

    /**
     * Validate date format
     */
    public function validateDate($date, $field = 'date')
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            $this->errors[$field] = ucfirst($field) . ' must be in YYYY-MM-DD format';
            return false;
        }
        return true;
    }

    /**
     * Validate allowed values
     */
    public function validateIn($value, $allowed, $field = 'value')
    {
        if (!in_array($value, $allowed)) {
            $allowedStr = implode(', ', $allowed);
            $this->errors[$field] = ucfirst($field) . " must be one of: $allowedStr";
            return false;
        }
        return true;
    }

    /**
     * Validate phone number
     */
    public function validatePhone($phone, $field = 'phone')
    {
        if (!preg_match('/^[+]?[\d\s\-()]{10,20}$/', $phone)) {
            $this->errors[$field] = 'Invalid phone number format';
            return false;
        }
        return true;
    }

    /**
     * Validate password strength
     */
    public function validatePassword($password)
    {
        if (strlen($password) < 8) {
            $this->errors['password'] = 'Password must be at least 8 characters';
            return false;
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $this->errors['password'] = 'Password must contain at least one uppercase letter';
            return false;
        }
        if (!preg_match('/[a-z]/', $password)) {
            $this->errors['password'] = 'Password must contain at least one lowercase letter';
            return false;
        }
        if (!preg_match('/[0-9]/', $password)) {
            $this->errors['password'] = 'Password must contain at least one number';
            return false;
        }
        return true;
    }

    /**
     * Sanitize string output
     */
    public static function sanitize($value)
    {
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get all validation errors
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Check if has errors
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }

    /**
     * Reset errors
     */
    public function reset()
    {
        $this->errors = [];
        return $this;
    }

    /* ======================================================================
     * BACKWARD COMPATIBILITY — Static API used by existing code
     * Uses a per-request singleton to avoid race conditions.
     * ====================================================================== */
    private static $instance = null;

    private static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function required($data, $fields)
    {
        return self::instance()->validateRequired($data, $fields);
    }

    public static function email($email)
    {
        return self::instance()->validateEmail($email);
    }

    public static function minLength($value, $min, $field = 'value')
    {
        return self::instance()->validateMinLength($value, $min, $field);
    }

    public static function maxLength($value, $max, $field = 'value')
    {
        return self::instance()->validateMaxLength($value, $max, $field);
    }

    public static function numeric($value, $field = 'value')
    {
        return self::instance()->validateNumeric($value, $field);
    }

    public static function range($value, $min, $max, $field = 'value')
    {
        return self::instance()->validateRange($value, $min, $max, $field);
    }

    public static function date($date, $field = 'date')
    {
        return self::instance()->validateDate($date, $field);
    }

    public static function in($value, $allowed, $field = 'value')
    {
        return self::instance()->validateIn($value, $allowed, $field);
    }

    public static function phone($phone, $field = 'phone')
    {
        return self::instance()->validatePhone($phone, $field);
    }

    public static function password($password)
    {
        return self::instance()->validatePassword($password);
    }

    public static function errors()
    {
        return self::instance()->getErrors();
    }

    public static function hasErrors()
    {
        return self::instance()->hasErrors();
    }

    public static function reset()
    {
        self::$instance = new self();
    }
}

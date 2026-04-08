const twilio = require('twilio');

// Initialize Twilio client only if credentials exist
let twilioClient = null;
if (process.env.TWILIO_ACCOUNT_SID && process.env.TWILIO_AUTH_TOKEN && process.env.TWILIO_PHONE_NUMBER) {
  try {
    twilioClient = twilio(process.env.TWILIO_ACCOUNT_SID, process.env.TWILIO_AUTH_TOKEN);
    console.log('✅ SMS Service (Twilio) initialized');
  } catch (err) {
    console.warn('⚠️  Twilio client initialization failed. SMS will use mock mode.');
  }
}

/**
 * Send SMS
 * @param {Object} params - SMS parameters
 * @param {string} params.to - Recipient phone number
 * @param {string} params.message - Message content
 * @returns {Promise<Object>} - SMS send result
 */
async function sendSMS({ to, message }) {
  // Development mode - log to console instead of sending
  if (process.env.NODE_ENV === 'development' || !twilioClient) {
    console.log('\n📱 [SMS MOCK] Development Mode - SMS not actually sent');
    console.log('   To:', to);
    console.log('   Message:', message);
    console.log('   Length:', message.length, 'characters\n');

    // Simulate API delay
    await new Promise(resolve => setTimeout(resolve, 100));

    return {
      success: true,
      mock: true,
      message: 'SMS logged to console (development mode)',
      to,
      message
    };
  }

  // Production mode - send actual SMS
  try {
    const result = await twilioClient.messages.create({
      body: message,
      from: process.env.TWILIO_PHONE_NUMBER,
      to: to.startsWith('+') ? to : `+91${to}` // Default to India +91 if no country code
    });

    console.log('✅ SMS sent successfully:', result.sid);
    return {
      success: true,
      mock: false,
      sid: result.sid,
      to: result.to,
      dateCreated: result.dateCreated
    };
  } catch (err) {
    console.error('❌ SMS sending failed:', err.message);

    // Don't throw error - SMS failure shouldn't break main functionality
    return {
      success: false,
      mock: false,
      error: err.message,
      to
    };
  }
}

/**
 * Send bulk SMS
 * @param {Array} messages - Array of {to, message} objects
 * @returns {Promise<Array>} - Results for each SMS
 */
async function sendBulkSMS(messages) {
  const results = [];

  for (const msg of messages) {
    const result = await sendSMS(msg);
    results.push(result);

    // Add small delay to avoid rate limiting
    if (!result.mock) {
      await new Promise(resolve => setTimeout(resolve, 200));
    }
  }

  return results;
}

/**
 * Validate phone number format
 * @param {string} phone - Phone number to validate
 * @returns {boolean} - Is valid phone number
 */
function isValidPhone(phone) {
  if (!phone) return false;
  // Basic validation for international format
  const phoneRegex = /^\+?[1-9]\d{1,14}$/;
  return phoneRegex.test(phone.replace(/[\s\-\(\)]/g, ''));
}

module.exports = {
  send: sendSMS,
  sendSMS,
  sendBulkSMS,
  isValidPhone
};

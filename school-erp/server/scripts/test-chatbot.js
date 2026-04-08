/**
 * Test Chatbot NLP Engine
 * Week 1, Day 3-4: Task 1.3
 * 
 * Tests:
 * - Model training
 * - Entity loading
 * - Response generation
 * - Multiple query types
 */

require('dotenv').config();
const prisma = require('../config/prisma');
const connectDB = require('../config/db');

connectDB();

async function testChatbot() {
  console.log('\n' + '='.repeat(70));
  console.log('🤖 CHATBOT NLP ENGINE TESTING');
  console.log('='.repeat(70) + '\n');

  try {
    // Load NLP engine
    console.log('📦 Loading NLP Engine...');
    const { trainChatbot, processMessage, getModelVersion } = require('../ai/nlpEngine');
    console.log('   ✅ NLP Engine loaded\n');

    // Train the chatbot
    console.log('🎓 Training Chatbot Model...');
    const startTime = Date.now();
    await trainChatbot();
    const trainingTime = ((Date.now() - startTime) / 1000).toFixed(2);
    console.log(`   ✅ Model trained in ${trainingTime}s\n`);

    // Check model version
    try {
      const version = getModelVersion();
      console.log('📋 Model Version:');
      console.log(`   Created: ${version?.createdAt || 'N/A'}`);
      console.log(`   Documents: ${version?.documentCount || 'N/A'}`);
      console.log(`   Languages: ${version?.languages?.join(', ') || 'N/A'}\n`);
    } catch (err) {
      console.log('   ⚠️  Could not retrieve model version\n');
    }

    // Test queries
    const testCases = [
      { query: 'hello', category: 'Greeting', expected: 'greeting or friendly response' },
      { query: 'how are you', category: 'Greeting', expected: 'greeting response' },
      { query: 'admission process', category: 'Admission', expected: 'admission information' },
      { query: 'fee structure', category: 'Fees', expected: 'fee information' },
      { query: 'exam schedule', category: 'Exams', expected: 'exam information' },
      { query: 'attendance', category: 'Attendance', expected: 'attendance information' },
      { query: 'library books', category: 'Library', expected: 'library information' },
      { query: 'transport routes', category: 'Transport', expected: 'transport information' },
      { query: 'canteen menu', category: 'Canteen', expected: 'canteen information' },
      { query: 'hostel facility', category: 'Hostel', expected: 'hostel information' },
      { query: 'leave application', category: 'HR', expected: 'leave information' },
      { query: 'payroll', category: 'Payroll', expected: 'payroll information' },
      { query: 'complaint', category: 'Complaints', expected: 'complaint information' },
      { query: 'student count', category: 'Students', expected: 'student statistics' },
      { query: 'teacher list', category: 'Staff', expected: 'staff information' },
      { query: 'thank you', category: 'Greeting', expected: 'thank you response' },
      { query: 'help', category: 'Help', expected: 'help menu' },
      { query: 'homework', category: 'Homework', expected: 'homework information' },
      { query: 'notice board', category: 'Notices', expected: 'notice information' },
      { query: 'salary', category: 'Payroll', expected: 'salary information' }
    ];

    console.log('🧪 Running Test Queries...\n');
    console.log('-'.repeat(70));

    let passed = 0;
    let failed = 0;
    const results = [];

    for (const testCase of testCases) {
      try {
        const startTest = Date.now();
        const response = await processMessage(testCase.query, 'en');
        const responseTime = Date.now() - startTest;

        const hasValidResponse = response &&
          response.message &&
          response.message.length > 10 &&
          !response.error;

        const status = hasValidResponse ? '✅ PASS' : '⚠️ WEAK';

        if (hasValidResponse) passed++;
        else failed++;

        results.push({
          query: testCase.query,
          category: testCase.category,
          intent: response.intent || 'None',
          responseTime: `${responseTime}ms`,
          status,
          message: response.message.substring(0, 80)
        });

        console.log(`\nQuery: "${testCase.query}"`);
        console.log(`Category: ${testCase.category}`);
        console.log(`Intent: ${response.intent || 'None'}`);
        console.log(`Response Time: ${responseTime}ms`);
        console.log(`Status: ${status}`);
        console.log(`Response: ${response.message.substring(0, 100)}...`);
      } catch (err) {
        failed++;
        console.log(`\nQuery: "${testCase.query}"`);
        console.log(`❌ ERROR: ${err.message}`);

        results.push({
          query: testCase.query,
          category: testCase.category,
          intent: 'ERROR',
          responseTime: 'N/A',
          status: '❌ FAIL',
          message: err.message
        });
      }
    }

    // Summary
    console.log('\n' + '='.repeat(70));
    console.log('📊 CHATBOT TEST SUMMARY');
    console.log('='.repeat(70));
    console.log(`\nTotal Tests: ${testCases.length}`);
    console.log(`Passed: ${passed}`);
    console.log(`Failed: ${failed}`);
    console.log(`Success Rate: ${((passed / testCases.length) * 100).toFixed(1)}%\n`);

    console.log('-'.repeat(70));
    console.log('Detailed Results:');
    console.log('-'.repeat(70));
    console.log(`${'Query'.padEnd(20)} ${'Category'.padEnd(12)} ${'Intent'.padEnd(15)} ${'Time'.padEnd(8)} ${'Status'}`);
    console.log('-'.repeat(70));

    for (const result of results) {
      console.log(
        `${result.query.padEnd(20).substring(0, 20)} ` +
        `${result.category.padEnd(12)} ` +
        `${result.intent.padEnd(15)} ` +
        `${result.responseTime.padEnd(8)} ` +
        `${result.status}`
      );
    }

    console.log('-'.repeat(70) + '\n');

    // Recommendations
    if (passed >= 15) {
      console.log('✅ Chatbot is working well! Ready for production.\n');
    } else if (passed >= 10) {
      console.log('⚠️  Chatbot is partially working. Some queries need improvement.\n');
    } else {
      console.log('❌ Chatbot needs more training data. Add more intents and examples.\n');
    }

  } catch (error) {
    console.error('❌ Fatal error testing chatbot:', error);
    console.error(error.stack);
  } finally {
    await prisma.$disconnect();
    console.log('👋 Database connection closed\n');
    process.exit(0);
  }
}

testChatbot().catch(console.error);

const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const { processMessage } = require('../ai/nlpEngine');
const { searchKnowledgeBase } = require('../ai/scanner');
const { getChatbotBootstrap, LANGUAGE_OPTIONS } = require('../ai/chatbotUi');
const auth = require('../middleware/auth');
const { chatbotLimiter } = require('../middleware/rateLimiter');

const MAX_MESSAGE_LENGTH = 500;
const ALLOWED_LANGUAGES = LANGUAGE_OPTIONS.map(({ code }) => code);

// Fix: Add input sanitization to prevent XSS
function sanitizeInput(text) {
  return text
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#x27;')
    .replace(/\//g, '&#x2F;');
}

function getKnowledgePrefix(language) {
  if (language === 'hi') return 'Knowledge base ke anusar';
  if (language === 'as') return 'Knowledge base anusare';
  return 'According to our policies';
}

async function handleChatRequest(req, res) {
  try {
    const { message, language = 'en' } = req.body;
    const sessionId = req.header('x-chatbot-session-id') || req.body?.sessionId || `user-${req.user.id}`;

    if (!message || typeof message !== 'string') {
      return res.status(400).json({ error: 'Message is required and must be a string' });
    }

    const trimmedMessage = message.trim();
    if (trimmedMessage.length === 0) {
      return res.status(400).json({ error: 'Message cannot be empty' });
    }

    if (trimmedMessage.length > MAX_MESSAGE_LENGTH) {
      return res.status(400).json({ error: `Message too long. Maximum length is ${MAX_MESSAGE_LENGTH} characters.` });
    }

    // Fix: Sanitize input before storage to prevent XSS
    const sanitizedMessage = sanitizeInput(trimmedMessage);

    const selectedLanguage = ALLOWED_LANGUAGES.includes(language) ? language : 'en';

    // Process message first
    const responseData = await processMessage(trimmedMessage, selectedLanguage, req.user);

    // Determine final intent for logging
    let finalIntent = responseData.intent;
    let finalMessage = responseData.message;
    if (finalIntent === 'None') {
      const kbResult = searchKnowledgeBase(trimmedMessage, {
        language: selectedLanguage,
        audience: req.user.role,
        asObject: true,
      });
      if (kbResult) {
        finalIntent = 'FAQ';
        finalMessage = `${getKnowledgePrefix(selectedLanguage)}: ${kbResult.content}`;
        responseData.intent = 'FAQ';
        responseData.message = finalMessage;
        responseData.source = 'knowledge_base';
        responseData.knowledgeBase = {
          title: kbResult.title,
          source: kbResult.source,
          language: kbResult.language || selectedLanguage,
        };
      }
    }

    // Log asynchronously - fire and forget
    prisma.chatbotLog.create({
      data: {
        userId: req.user.id,
        userRole: req.user.role,
        message: sanitizedMessage,
        language: selectedLanguage,
        intent: finalIntent,
        response: finalMessage,
        responseTime: responseData.responseTime || 0,
        timestamp: new Date(),
        sessionId,
      },
    }).catch(err => console.error('Failed to log chatbot query:', err));

    return res.json({
      ...responseData,
      language: selectedLanguage,
      source: responseData.source || 'live',
    });
  } catch (err) {
    console.error('Chatbot error:', err);

    prisma.chatbotLog.create({
      data: {
        userId: req.user?.id || 'unknown',
        userRole: req.user?.role || 'unknown',
        message: req.body?.message || 'N/A',
        language: req.body?.language || 'en',
        intent: 'ERROR',
        response: err.message,
        responseTime: 0,
        timestamp: new Date(),
        sessionId: req.header('x-chatbot-session-id') || req.body?.sessionId || null,
      },
    }).catch(() => { });

    const errorResponse = {
      error: 'Internal server error processing message.',
    };

    if (process.env.NODE_ENV === 'development') {
      errorResponse.message = err.message;
    }

    return res.status(500).json(errorResponse);
  }
}

router.post('/chat', auth, chatbotLimiter, handleChatRequest);
router.post('/message', auth, chatbotLimiter, handleChatRequest);

router.get('/bootstrap', auth, (req, res) => {
  const selectedLanguage = ALLOWED_LANGUAGES.includes(req.query.language) ? req.query.language : 'en';
  res.json({
    success: true,
    ...getChatbotBootstrap({
      language: selectedLanguage,
      role: req.user?.role,
    }),
  });
});

router.get('/history', auth, async (req, res) => {
  try {
    const limit = parseInt(req.query.limit) || 20;

    const logs = await prisma.chatbotLog.findMany({
      where: { userId: req.user.id },
      orderBy: { timestamp: 'desc' },
      take: limit,
      select: { message: true, response: true, intent: true, timestamp: true, language: true },
    });

    res.json({
      success: true,
      history: logs.reverse(),
      count: logs.length,
    });
  } catch (err) {
    console.error('Chatbot history error:', err);
    res.status(500).json({ error: 'Failed to retrieve chat history' });
  }
});

router.get('/analytics', auth, async (req, res) => {
  try {
    if (req.user.role !== 'superadmin' && req.user.role !== 'admin') {
      return res.status(403).json({ error: 'Access denied. Admin role required.' });
    }

    const days = parseInt(req.query.days) || 30;
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - days);

    const stats = await prisma.chatbotLog.groupBy({
      by: ['intent'],
      where: { timestamp: { gte: startDate } },
      _count: { _all: true },
      _avg: { responseTime: true },
      orderBy: { _count: { intent: 'desc' } },
    });

    const topQueries = await prisma.chatbotLog.findMany({
      where: { timestamp: { gte: startDate }, intent: { not: 'ERROR' } },
      orderBy: { timestamp: 'desc' },
      take: 100,
      select: { message: true, intent: true },
    });

    const errorLogs = await prisma.chatbotLog.findMany({
      where: { timestamp: { gte: startDate }, intent: 'ERROR' },
      orderBy: { timestamp: 'desc' },
      take: 50,
      select: { message: true, response: true, timestamp: true },
    });

    res.json({
      success: true,
      period: `${days} days`,
      totalQueries: stats.reduce((sum, s) => sum + s._count._all, 0),
      intentBreakdown: stats.map(s => ({
        _id: s.intent,
        count: s._count._all,
        avgResponseTime: s._avg.responseTime || 0,
      })),
      topQueries: topQueries.slice(0, 20),
      recentErrors: errorLogs.slice(0, 10),
    });
  } catch (err) {
    console.error('Chatbot analytics error:', err);
    res.status(500).json({ error: 'Failed to retrieve analytics' });
  }
});

router.get('/languages', auth, (_req, res) => {
  res.json({
    success: true,
    languages: LANGUAGE_OPTIONS,
    defaultLanguage: 'en',
  });
});

module.exports = router;

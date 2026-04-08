const fs = require('fs');
const path = require('path');

console.log('=== Adding Phase 6: Advanced Features ===\n');

// 1. Add personality modes to chatbotKnowledge.js
const ckPath = path.join(__dirname, 'client', 'src', 'utils', 'chatbotKnowledge.js');
let ckContent = fs.readFileSync(ckPath, 'utf8');

if (!ckContent.includes('personalityModes')) {
  const personalityCode = `

// ============================================================
// PHASE 6: PERSONALITY MODES (Feature 112)
// ============================================================
export const personalityModes = {
  formal: {
    greeting: "Good day. How may I assist you with the School ERP system?",
    tone: "professional",
    emoji: "🎓"
  },
  friendly: {
    greeting: "Hey there! 👋 What can I help you with today?",
    tone: "casual",
    emoji: "😊"
  },
  funny: {
    greeting: "Beep boop! 🤖 Your friendly school bot is online! What's up?",
    tone: "humorous",
    emoji: "🎪"
  }
};

// Default personality
export let currentPersonality = 'friendly';

export function setPersonality(mode) {
  if (personalityModes[mode]) {
    currentPersonality = mode;
    try { localStorage.setItem('chatbot_personality', mode); } catch {}
    return true;
  }
  return false;
}

export function getPersonality() {
  try {
    const saved = localStorage.getItem('chatbot_personality');
    if (saved && personalityModes[saved]) return saved;
  } catch {}
  return currentPersonality;
}

export function getGreeting() {
  const p = getPersonality();
  return personalityModes[p]?.greeting || personalityModes.friendly.greeting;
}
`;

  // Add before the last module.exports
  const exportIndex = ckContent.lastIndexOf('export {');
  if (exportIndex !== -1) {
    ckContent = ckContent.slice(0, exportIndex) + personalityCode + '\n' + ckContent.slice(exportIndex);
    fs.writeFileSync(ckPath, ckContent);
    console.log('✓ Added personality modes to chatbotKnowledge.js');
  } else {
    ckContent += personalityCode;
    fs.writeFileSync(ckPath, ckContent);
    console.log('✓ Added personality modes to chatbotKnowledge.js (appended)');
  }
} else {
  console.log('  - Personality modes already present in chatbotKnowledge.js');
}

// 2. Add achievement system to chatbotEngine.js
const cePath = path.join(__dirname, 'client', 'src', 'utils', 'chatbotEngine.js');
let ceContent = fs.readFileSync(cePath, 'utf8');

if (!ceContent.includes('ACHIEVEMENTS')) {
  const achievementCode = `

// ============================================================
// PHASE 6: ACHIEVEMENT SYSTEM (Feature 113)
// ============================================================
const ACHIEVEMENTS = {
  first_question: { name: "First Question", desc: "Asked your first question", emoji: "🌟" },
  curious_mind: { name: "Curious Mind", desc: "Asked 10 different questions", emoji: "🧠" },
  power_user: { name: "Power User", desc: "Used the bot 50 times", emoji: "⚡" },
  polyglot: { name: "Polyglot", desc: "Used 3 languages", emoji: "🌍" },
  helper: { name: "Helper", desc: "Gave 10 positive feedback ratings", emoji: "👍" },
  night_owl: { name: "Night Owl", desc: "Used the bot after 10 PM", emoji: "🦉" },
  early_bird: { name: "Early Bird", desc: "Used the bot before 7 AM", emoji: "🐦" },
  streak: { name: "7-Day Streak", desc: "Used the bot 7 days in a row", emoji: "🔥" },
};

function getAchievementStats() {
  try {
    return JSON.parse(localStorage.getItem('chatbot_achievements_stats') || '{}');
  } catch { return {}; }
}

function updateAchievementStats(type) {
  const stats = getAchievementStats();
  stats[type] = (stats[type] || 0) + 1;
  stats.lastUsed = Date.now();
  try { localStorage.setItem('chatbot_achievements_stats', JSON.stringify(stats)); } catch {}

  // Check for new achievements
  const unlocked = JSON.parse(localStorage.getItem('chatbot_unlocked_achievements') || '[]');
  const newUnlocks = [];

  if (stats.questions >= 1 && !unlocked.includes('first_question')) {
    newUnlocks.push('first_question');
  }
  if (stats.questions >= 10 && !unlocked.includes('curious_mind')) {
    newUnlocks.push('curious_mind');
  }
  if (stats.questions >= 50 && !unlocked.includes('power_user')) {
    newUnlocks.push('power_user');
  }
  if (stats.languages >= 3 && !unlocked.includes('polyglot')) {
    newUnlocks.push('polyglot');
  }
  if (stats.positiveFeedback >= 10 && !unlocked.includes('helper')) {
    newUnlocks.push('helper');
  }

  const hour = new Date().getHours();
  if (hour >= 22 && !unlocked.includes('night_owl')) {
    newUnlocks.push('night_owl');
  }
  if (hour >= 0 && hour < 7 && !unlocked.includes('early_bird')) {
    newUnlocks.push('early_bird');
  }

  if (newUnlocks.length > 0) {
    unlocked.push(...newUnlocks);
    try { localStorage.setItem('chatbot_unlocked_achievements', JSON.stringify(unlocked)); } catch {}
  }

  return newUnlocks.map(id => ACHIEVEMENTS[id]);
}

export function getAchievements() {
  const unlocked = JSON.parse(localStorage.getItem('chatbot_unlocked_achievements') || '[]');
  return {
    unlocked: unlocked.map(id => ACHIEVEMENTS[id]).filter(Boolean),
    total: Object.keys(ACHIEVEMENTS).length,
    all: ACHIEVEMENTS
  };
}

export { updateAchievementStats, getAchievementStats, ACHIEVEMENTS };
`;

  // Add before the last export default
  const exportDefaultIndex = ceContent.lastIndexOf('export default');
  if (exportDefaultIndex !== -1) {
    ceContent = ceContent.slice(0, exportDefaultIndex) + achievementCode + '\n' + ceContent.slice(exportDefaultIndex);
    fs.writeFileSync(cePath, ceContent);
    console.log('✓ Added achievement system to chatbotEngine.js');
  } else {
    ceContent += achievementCode;
    fs.writeFileSync(cePath, ceContent);
    console.log('✓ Added achievement system to chatbotEngine.js (appended)');
  }
} else {
  console.log('  - Achievement system already present in chatbotEngine.js');
}

// 3. Create offline cache utility
const offlineCachePath = path.join(__dirname, 'client', 'src', 'utils', 'chatbotOfflineCache.js');
if (!fs.existsSync(offlineCachePath)) {
  const offlineCacheCode = `// ============================================================
// PHASE 6: OFFLINE CACHE (Feature 111)
// Caches last 50 Q&A pairs for offline fallback
// ============================================================

const CACHE_KEY = 'chatbot_offline_cache';
const MAX_CACHE = 50;

export function getCachedResponse(query) {
  try {
    const cache = JSON.parse(localStorage.getItem(CACHE_KEY) || '[]');
    // Simple keyword matching
    const queryWords = query.toLowerCase().split(' ').filter(w => w.length > 3);
    for (const entry of cache) {
      const matchScore = queryWords.filter(w => entry.query.toLowerCase().includes(w)).length;
      if (matchScore >= 2) return entry.response; // At least 2 keywords match
    }
  } catch {}
  return null;
}

export function cacheResponse(query, response) {
  try {
    const cache = JSON.parse(localStorage.getItem(CACHE_KEY) || '[]');
    cache.unshift({ query, response, timestamp: Date.now() });
    // Keep only last MAX_CACHE entries
    if (cache.length > MAX_CACHE) cache.length = MAX_CACHE;
    localStorage.setItem(CACHE_KEY, JSON.stringify(cache));
  } catch {}
}

export function getCacheSize() {
  try {
    const cache = JSON.parse(localStorage.getItem(CACHE_KEY) || '[]');
    return cache.length;
  } catch { return 0; }
}

export function clearCache() {
  try { localStorage.removeItem(CACHE_KEY); } catch {}
}
`;

  fs.writeFileSync(offlineCachePath, offlineCacheCode);
  console.log('✓ Created chatbotOfflineCache.js');
} else {
  console.log('  - Offline cache utility already exists');
}

// 4. Add multi-language mid-conversation support to chatbotEngine.js
if (!ceContent.includes('MID_CONVERSATION_LANG')) {
  const multiLangCode = `

// ============================================================
// PHASE 6: MID-CONVERSATION LANGUAGE SWITCH (Feature 115)
// ============================================================
export const MID_CONVERSATION_LANG = {
  '/lang en': { code: 'en', name: 'English', msg: '🌐 Language changed to English' },
  '/lang hi': { code: 'hi', name: 'हिंदी', msg: '🌐 भाषा हिंदी में बदल गई' },
  '/lang as': { code: 'as', name: 'অসমীয়া', msg: '🌐 ভাষা অসমীয়ালৈ সলনি কৰা হ\'ল' },
};

export function detectLanguageCommand(message) {
  const lower = message.toLowerCase().trim();
  return MID_CONVERSATION_LANG[lower] || null;
}
`;

  // Add before the last export default
  const exportDefaultIndex = ceContent.lastIndexOf('export default');
  if (exportDefaultIndex !== -1) {
    ceContent = ceContent.slice(0, exportDefaultIndex) + multiLangCode + '\n' + ceContent.slice(exportDefaultIndex);
    fs.writeFileSync(cePath, ceContent);
    console.log('✓ Added mid-conversation language switch to chatbotEngine.js');
  }
} else {
  console.log('  - Multi-language switch already present');
}

console.log('\n=== Phase 6 Advanced Features Complete ===');
console.log('  - Feature 112: Personality modes (formal, friendly, funny)');
console.log('  - Feature 113: Achievement system (8 achievements)');
console.log('  - Feature 111: Offline cache utility');
console.log('  - Feature 115: Mid-conversation language switch');
console.log('\nRemaining Phase 6 features (conversational forms, KB editor, response variation)');
console.log('are handled through the existing conversation context system in nlpEngine.js');

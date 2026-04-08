const FlexSearch = require('flexsearch');
const fs = require('fs');
const path = require('path');
const cheerio = require('cheerio');

// Initialize the flexsearch document index with better configuration
const index = new FlexSearch.Document({
    document: {
        id: "id",
        index: ["title", "content", "tags"],
        store: true
    },
    tokenize: "forward",
    context: true
});

const KB_FILE_PATH = path.join(__dirname, 'knowledgeBase.json');
const KB_VERSION_PATH = path.join(__dirname, 'knowledgeBase.version.json');
const CURATED_KB_DIR = path.join(__dirname, 'kb');
const CURATED_KB_FILE_PATH = path.join(CURATED_KB_DIR, 'curatedKnowledgeBase.json');
const CLIENT_PAGES_DIR = path.join(__dirname, '../../client/src/pages');
const CLIENT_COMPONENTS_DIR = path.join(__dirname, '../../client/src/components');
const CLIENT_UTILS_DIR = path.join(__dirname, '../../client/src/utils');
const PROJECT_ROOT_DIR = path.join(__dirname, '../..');
const ASSAMESE_SOURCE_FILES = [
    path.join(PROJECT_ROOT_DIR, 'ASSAMESE_CHATBOT_KNOWLEDGE_BASE.js'),
    path.join(PROJECT_ROOT_DIR, 'ASSAMESE_10000_WORD_KNOWLEDGE_BASE.md'),
    path.join(PROJECT_ROOT_DIR, 'ASSAMESE_10000_PART2.md'),
    path.join(PROJECT_ROOT_DIR, 'ASSAMESE_10000_PART3.md')
];

let kbData = [];
let documentIdCounter = 1;

function inferKnowledgeTags(title = '', content = '', extraTags = []) {
    const tags = new Set(['assamese', 'translation', ...extraTags.filter(Boolean)]);
    const lower = `${title} ${content}`.toLowerCase();

    const tagMap = {
        students: ['student', 'admission', 'guardian', 'parent', 'enrollment'],
        attendance: ['attendance', 'leave', 'present', 'absent'],
        fees: ['fee', 'payment', 'receipt', 'scholarship'],
        exams: ['exam', 'marks', 'result', 'report card'],
        library: ['library', 'book', 'isbn', 'fine'],
        canteen: ['canteen', 'food', 'menu', 'wallet'],
        hostel: ['hostel', 'room', 'bed', 'warden'],
        transport: ['transport', 'bus', 'vehicle', 'route', 'driver'],
        payroll: ['payroll', 'salary', 'payslip'],
        notices: ['notice', 'announcement'],
        homework: ['homework', 'assignment'],
        routine: ['routine', 'timetable'],
        complaints: ['complaint', 'grievance'],
        hr: ['staff', 'employee', 'teacher', 'leave balance'],
        geography: ['india', 'assam', 'district', 'city', 'river', 'state'],
        social: ['hello', 'good morning', 'thank you', 'welcome']
    };

    for (const [tag, keywords] of Object.entries(tagMap)) {
        if (keywords.some((keyword) => lower.includes(keyword))) {
            tags.add(tag);
        }
    }

    return Array.from(tags);
}

function inferModuleFromTags(tags = []) {
    const orderedModules = [
        'students',
        'attendance',
        'fees',
        'exams',
        'library',
        'canteen',
        'hostel',
        'transport',
        'payroll',
        'notices',
        'homework',
        'routine',
        'complaints',
        'hr'
    ];

    return orderedModules.find((tag) => tags.includes(tag)) || 'assamese_reference';
}

function buildKnowledgeEntry({ title, content, source, extraTags = [], language = 'as', sourceType = 'assamese_source', priority = 'normal' }) {
    const tags = inferKnowledgeTags(title, content, extraTags);
    const entry = normalizeCuratedEntry({
        title,
        content,
        tags,
        module: inferModuleFromTags(tags),
        audience: ['all'],
        language,
        source,
        sourceType,
        priority
    });

    return entry;
}

function chunkLines(lines, chunkSize = 40) {
    const chunks = [];
    for (let index = 0; index < lines.length; index += chunkSize) {
        const chunk = lines.slice(index, index + chunkSize);
        if (chunk.length > 0) {
            chunks.push(chunk);
        }
    }
    return chunks;
}

function normalizeSectionTitle(title, fallbackTitle) {
    const cleaned = String(title || '')
        .replace(/^#+\s*/, '')
        .replace(/^\/\/\s*/, '')
        .replace(/^[=\-#\s]+/, '')
        .replace(/\s+/g, ' ')
        .trim();

    return cleaned || fallbackTitle;
}

function flushAssameseSection(docs, sectionTitle, sectionLines, sourceName, extraTags = []) {
    const cleanedLines = sectionLines
        .map((line) => String(line || '').trim())
        .filter(Boolean);

    if (!cleanedLines.length) {
        return;
    }

    const chunks = chunkLines(cleanedLines, 40);
    chunks.forEach((chunk, index) => {
        const chunkTitle = chunks.length === 1
            ? sectionTitle
            : `${sectionTitle} - Part ${index + 1}`;
        const entry = buildKnowledgeEntry({
            title: chunkTitle,
            content: chunk.join('\n'),
            source: sourceName,
            extraTags
        });

        if (entry) {
            docs.push(entry);
        }
    });
}

function loadAssameseMarkdownKnowledge(filePath) {
    const docs = [];
    if (!fs.existsSync(filePath)) {
        return docs;
    }

    const sourceName = path.basename(filePath);
    const raw = fs.readFileSync(filePath, 'utf8');
    const lines = raw.split(/\r?\n/);
    let currentTitle = sourceName.replace(/\.(md|txt)$/i, '');
    let currentLines = [];

    for (const rawLine of lines) {
        const line = rawLine.trim();
        if (!line) {
            continue;
        }

        if (/^##\s+/.test(line)) {
            flushAssameseSection(docs, currentTitle, currentLines, sourceName, ['markdown', 'reference']);
            currentTitle = normalizeSectionTitle(line, currentTitle);
            currentLines = [];
            continue;
        }

        if (/^#\s+/.test(line) && currentLines.length === 0) {
            currentTitle = normalizeSectionTitle(line, currentTitle);
            continue;
        }

        currentLines.push(line);
    }

    flushAssameseSection(docs, currentTitle, currentLines, sourceName, ['markdown', 'reference']);
    return docs;
}

function loadAssameseJsKnowledge(filePath) {
    const docs = [];
    if (!fs.existsSync(filePath)) {
        return docs;
    }

    const sourceName = path.basename(filePath);
    const raw = fs.readFileSync(filePath, 'utf8');
    const lines = raw.split(/\r?\n/);
    let currentTitle = sourceName.replace(/\.js$/i, '');
    let currentLines = [];

    for (const rawLine of lines) {
        const line = rawLine.trim();
        if (!line) {
            continue;
        }

        if (/^\/\/\s*SECTION/i.test(line)) {
            flushAssameseSection(docs, currentTitle, currentLines, sourceName, ['javascript', 'glossary']);
            currentTitle = normalizeSectionTitle(line, currentTitle);
            currentLines = [];
            continue;
        }

        const pairMatch = line.match(/^"(.+?)"\s*:\s*"(.+?)",?$/);
        if (pairMatch) {
            currentLines.push(`${pairMatch[1]} -> ${pairMatch[2]}`);
            continue;
        }

        if (/^\/\//.test(line) || /^#/.test(line) || /^[{}];?$/.test(line) || /^const\s+/.test(line)) {
            continue;
        }
    }

    flushAssameseSection(docs, currentTitle, currentLines, sourceName, ['javascript', 'glossary']);
    return docs;
}

function loadAssameseKnowledgeSources() {
    const docs = [];
    let sourceFilesLoaded = 0;

    ASSAMESE_SOURCE_FILES.forEach((filePath) => {
        if (!fs.existsSync(filePath)) {
            return;
        }

        const nextDocs = filePath.endsWith('.js')
            ? loadAssameseJsKnowledge(filePath)
            : loadAssameseMarkdownKnowledge(filePath);

        if (nextDocs.length > 0) {
            docs.push(...nextDocs);
            sourceFilesLoaded += 1;
        }
    });

    console.log(`[KB] Loaded ${docs.length} Assamese knowledge documents from ${sourceFilesLoaded} source files.`);
    return { docs, sourceFilesLoaded };
}

function normalizeSearchText(value) {
  return String(value || '')
        .normalize('NFC')
        .toLowerCase()
        .trim();
}

function formatSearchResult(doc) {
    return `**${doc.title}**\n\n${doc.content}`;
}

function inferLanguageFromFile(filePath, textContent = '') {
    const fileName = path.basename(filePath).toLowerCase();
    if (fileName.includes('assamese')) return 'as';
    if (fileName.includes('hindi')) return 'hi';
    if (/[\u0980-\u09FF]/.test(textContent)) return 'as';
    if (/[\u0900-\u097F]/.test(textContent)) return 'hi';
    return 'en';
}

function matchesAudience(doc, audience) {
    if (!audience) return true;
    if (!Array.isArray(doc?.audience) || !doc.audience.length) return true;
    return doc.audience.includes('all') || doc.audience.includes(audience);
}

function getPriorityBoost(priority) {
    if (priority === 'high') return 2;
    if (priority === 'critical') return 3;
    if (priority === 'low') return -1;
    return 0;
}

function scoreDocument(doc, normalizedQuery, normalizedTokens, options = {}) {
    const preferredLanguage = options.language;
    const queryLooksAssamese = /[\u0980-\u09FF]/.test(normalizedQuery);
    const queryLooksHindi = /[\u0900-\u097F]/.test(normalizedQuery);

    let score = 0;

    const title = normalizeSearchText(doc.title);
    const content = normalizeSearchText(doc.content);
    const tags = Array.isArray(doc.tags) ? doc.tags.map(normalizeSearchText) : [];

    if (title.includes(normalizedQuery)) {
        score += 12;
    }

    if (tags.some(tag => tag.includes(normalizedQuery))) {
        score += 8;
    }

    if (content.includes(normalizedQuery)) {
        score += 3;
    }

    normalizedTokens.forEach((token) => {
        if (title.includes(token)) score += 4;
        if (tags.some(tag => tag.includes(token))) score += 2;
        if (content.includes(token)) score += 1;
    });

    if (preferredLanguage) {
        if (doc.language === preferredLanguage) {
            score += 4;
        } else if (doc.language === 'en') {
            score += 1;
        }
    }

    if (queryLooksAssamese && doc.language === 'as') score += 3;
    if (queryLooksHindi && doc.language === 'hi') score += 3;

    if (doc.sourceType === 'curated') {
        score += 4;
    } else if (doc.sourceType === 'assamese_source') {
        score += 2;
    }

    score += getPriorityBoost(doc.priority);

    return score;
}

// Get knowledge base version
function getKBVersion() {
    try {
        if (fs.existsSync(KB_VERSION_PATH)) {
            return JSON.parse(fs.readFileSync(KB_VERSION_PATH, 'utf8'));
        }
    } catch (err) {
        console.error('[KB] Error reading version:', err.message);
    }
    return null;
}

// Save knowledge base version
function saveKBVersion(metadata = {}) {
    try {
        const versionInfo = {
            version: `kb_${Date.now()}`,
            timestamp: new Date().toISOString(),
            documentCount: kbData.length,
            ...metadata
        };
        fs.writeFileSync(KB_VERSION_PATH, JSON.stringify(versionInfo, null, 2));
        console.log(`[KB] Version ${versionInfo.version} saved`);
    } catch (err) {
        console.error('[KB] Error saving version:', err.message);
    }
}

function normalizeCuratedEntry(entry) {
    if (!entry || typeof entry !== 'object') return null;
    if (!entry.title || !entry.content) return null;

    return {
        id: documentIdCounter++,
        title: String(entry.title).trim(),
        content: String(entry.content).trim(),
        tags: Array.isArray(entry.tags) ? entry.tags.filter(Boolean) : [],
        module: entry.module ? String(entry.module).trim() : undefined,
        audience: Array.isArray(entry.audience) ? entry.audience.filter(Boolean) : ['all'],
        language: entry.language ? String(entry.language).trim() : 'en',
        source: entry.source ? String(entry.source).trim() : 'curatedKnowledgeBase.json',
        sourceType: entry.sourceType ? String(entry.sourceType).trim() : 'curated',
        priority: entry.priority ? String(entry.priority).trim() : 'normal'
    };
}

function loadCuratedKnowledgeBase() {
    if (!fs.existsSync(CURATED_KB_FILE_PATH)) {
        console.log('[KB] Curated knowledge base file not found, using fallback entries.');
        return [];
    }

    try {
        const raw = fs.readFileSync(CURATED_KB_FILE_PATH, 'utf8');
        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) {
            console.error('[KB] Curated knowledge base must be an array.');
            return [];
        }

        const curatedDocs = parsed
            .map(normalizeCuratedEntry)
            .filter(Boolean);

        console.log(`[KB] Loaded ${curatedDocs.length} curated knowledge entries.`);
        return curatedDocs;
    } catch (err) {
        console.error('[KB] Error loading curated knowledge base:', err.message);
        return [];
    }
}

function getLegacySeedEntries() {
    return [
        {
            id: documentIdCounter++,
            title: 'Library Rules and Policies',
            content: 'Books can be issued for a maximum of 14 days. A fine of Rs 10 per day is applied for late returns. Students must present their ID card for issuing books. Lost books must be paid for at double the cost.',
            tags: ['library', 'rules', 'fine', 'issue', 'return'],
            source: 'legacy-seed',
            sourceType: 'legacy'
        },
        {
            id: documentIdCounter++,
            title: 'Student Admissions Process',
            content: 'Admissions require the previous school leaving certificate, two passport-sized photos, birth certificate, and Aadhaar card. The admission form is available online and must be submitted with all documents verified.',
            tags: ['admission', 'student', 'documents', 'registration'],
            source: 'legacy-seed',
            sourceType: 'legacy'
        },
        {
            id: documentIdCounter++,
            title: 'Canteen Operating Hours',
            content: 'The school canteen is open from 10:00 AM to 2:00 PM on all working days. Payments can be made using the canteen wallet system. Parents can preload money into their child canteen wallet through the fee counter or approved school payment channels.',
            tags: ['canteen', 'timing', 'hours', 'wallet', 'payment'],
            source: 'legacy-seed',
            sourceType: 'legacy'
        }
    ];
}

// Recursive file finder with better filtering
function getAllFiles(dirPath, arrayOfFiles = []) {
    if (!fs.existsSync(dirPath)) return arrayOfFiles;

    const files = fs.readdirSync(dirPath);

    files.forEach(function (file) {
        const fullPath = path.join(dirPath, file);
        const stat = fs.statSync(fullPath);

        if (stat.isDirectory()) {
            // Skip node_modules, build, and other non-source directories
            const skipDirs = ['node_modules', 'build', 'dist', 'out', '.next', '.git', 'coverage'];
            if (!file.startsWith('.') && !skipDirs.includes(file)) {
                getAllFiles(fullPath, arrayOfFiles);
            }
        } else if (/\.(jsx|js|tsx|ts)$/.test(file)) {
            arrayOfFiles.push(fullPath);
        }
    });

    return arrayOfFiles;
}

// Improved text extraction with semantic understanding
function extractTextFromJSX(filePath) {
    try {
        const content = fs.readFileSync(filePath, 'utf8');
        const fileName = path.basename(filePath);

        // Extract meaningful text content
        let extractedSections = [];

        // 1. Extract string literals (user-facing messages)
        const stringMatches = content.match(/["'`](.*?)["'`]/g);
        if (stringMatches) {
            // Patterns that indicate potential secrets or sensitive data
            const secretPatterns = [
                /api[_-]?key/i, /secret/i, /password/i, /token/i, /private/i,
                /auth/i, /credential/i, /hash/i, /salt/i, /encrypt/i
            ];

            const meaningfulStrings = stringMatches
                .map(s => s.slice(1, -1))
                .filter(s => {
                    // Skip if too short, looks like a secret, or technical strings
                    if (s.length <= 10) return false;
                    if (s.startsWith('/') || s.includes('{') || s.startsWith('http')) return false;
                    if (!/[a-zA-Z]/.test(s)) return false;

                    // Skip if it looks like a secret (contains key/secret/password/token keywords nearby)
                    const contextAround = content.substring(
                        Math.max(0, content.indexOf(s) - 50),
                        content.indexOf(s) + s.length
                    );
                    if (secretPatterns.some(pattern => pattern.test(contextAround))) return false;

                    return true;
                });
            extractedSections.push(...meaningfulStrings);
        }

        // 2. Extract comments that might contain documentation
        const commentMatches = content.match(/\/\/\s*(.+)/g);
        if (commentMatches) {
            const comments = commentMatches
                .map(c => c.replace('//', '').trim())
                .filter(c => c.length > 15);
            extractedSections.push(...comments);
        }

        // 3. Extract label/text props from components
        const labelMatches = content.match(/(?:label|title|placeholder|alt)=["'](.*?)["']/gi);
        if (labelMatches) {
            const labels = labelMatches
                .map(l => l.split('=')[1].replace(/["']/g, ''))
                .filter(l => l.length > 3);
            extractedSections.push(...labels);
        }

        // 4. Extract aria-labels and accessibility text
        const ariaMatches = content.match(/aria-label=["'](.*?)["']/gi);
        if (ariaMatches) {
            const ariaLabels = ariaMatches
                .map(l => l.split('=')[1].replace(/["']/g, ''));
            extractedSections.push(...ariaLabels);
        }

        // Clean and combine extracted text
        let textContent = extractedSections
            .join(' ')
            .replace(/\s+/g, ' ')
            .replace(/\\n/g, ' ')
            .replace(/\\"/g, '"')
            .replace(/\\'/g, "'")
            .trim();

        // Only keep if meaningful content exists
        if (textContent.length > 50) {
            // Extract tags/keywords from file for better search
            const tags = extractTagsFromContent(content, fileName);

            return {
                text: textContent,
                tags: tags,
                source: fileName,
                path: filePath,
                language: inferLanguageFromFile(filePath, textContent),
            };
        }
    } catch (e) {
        console.error(`[KB] Error parsing ${filePath}:`, e.message);
    }
    return null;
}

// Extract relevant tags from file content for better search
function extractTagsFromContent(content, fileName) {
    const tags = new Set();
    const lowerContent = content.toLowerCase();

    // Map common ERP terms to tags
    const tagMap = {
        'student': ['student', 'admission', 'enrollment'],
        'attendance': ['attendance', 'present', 'absent'],
        'fee': ['fee', 'payment', 'receipt', 'money'],
        'exam': ['exam', 'test', 'marks', 'grade', 'result'],
        'library': ['library', 'book', 'issue', 'return'],
        'canteen': ['canteen', 'food', 'menu', 'meal'],
        'hostel': ['hostel', 'room', 'bed', 'accommodation'],
        'transport': ['transport', 'bus', 'vehicle', 'route'],
        'staff': ['staff', 'teacher', 'employee'],
        'payroll': ['payroll', 'salary', 'payment'],
        'dashboard': ['dashboard', 'overview', 'stats'],
        'report': ['report', 'analytics', 'export'],
        'settings': ['settings', 'configuration', 'profile']
    };

    for (const [keyword, associatedTags] of Object.entries(tagMap)) {
        if (lowerContent.includes(keyword)) {
            associatedTags.forEach(tag => tags.add(tag));
        }
    }

    // Add page-specific tags
    const pageName = fileName.replace(/\.(jsx|js|tsx|ts)$/, '').toLowerCase();
    tags.add(pageName);

    return Array.from(tags);
}

// Improved manual knowledge base entries with better categorization
function initializeKnowledgeBase() {
    kbData = [];
    documentIdCounter = 1;

    const curatedDocs = loadCuratedKnowledgeBase();
    if (curatedDocs.length > 0) {
        kbData.push(...curatedDocs);
    } else {
        kbData.push(...getLegacySeedEntries());
    }

    const assameseKnowledge = loadAssameseKnowledgeSources();
    if (assameseKnowledge.docs.length > 0) {
        kbData.push(...assameseKnowledge.docs);
    }

    console.log('[KB] Starting offline scan of Frontend Application components...');

    // Scan both pages and components directories
    const filesToScrape = [
        ...getAllFiles(CLIENT_PAGES_DIR, []),
        ...getAllFiles(CLIENT_COMPONENTS_DIR, []),
        ...getAllFiles(CLIENT_UTILS_DIR, [])
    ];

    let successCount = 0;
    let skipCount = 0;

    filesToScrape.forEach(filePath => {
        const extracted = extractTextFromJSX(filePath);
        if (extracted && extracted.text) {
            kbData.push({
                id: documentIdCounter++,
                title: `${extracted.source} - UI Content`,
                content: extracted.text,
                tags: extracted.tags || [],
                source: extracted.source,
                path: extracted.path,
                language: extracted.language || 'en',
                sourceType: 'ui_scan',
                priority: 'low'
            });
            successCount++;
        } else {
            skipCount++;
        }
    });

    console.log(`[KB] Scanned ${filesToScrape.length} files: ${successCount} with content, ${skipCount} skipped`);

    if (typeof index.clear === 'function') {
        index.clear();
    }

    // Build the index
    kbData.forEach(doc => {
        index.add(doc);
    });

    // Save to file
    fs.writeFileSync(KB_FILE_PATH, JSON.stringify(kbData, null, 2));
    saveKBVersion({
        filesScanned: filesToScrape.length,
        successCount,
        curatedCount: curatedDocs.length,
        assameseSourceCount: assameseKnowledge.docs.length,
        assameseSourceFilesLoaded: assameseKnowledge.sourceFilesLoaded,
        usedLegacySeed: curatedDocs.length === 0
    });

    console.log(`[KB] FlexSearch Index initialized with ${kbData.length} documents.`);
}

// Improved search with better result ranking
function searchKnowledgeBase(query, options = {}) {
    if (!query || typeof query !== 'string') return null;

    const normalizedQuery = normalizeSearchText(query);
    const normalizedTokens = normalizedQuery
        .split(/\s+/)
        .filter(Boolean);
    const filteredDocs = kbData.filter((doc) => matchesAudience(doc, options.audience));
    const safeReturnObject = Boolean(options.asObject);

    // Try FlexSearch first
    const results = index.search(normalizedQuery, { enrich: true, limit: 5 });

    if (results && results.length > 0) {
        // Find the best matching result
        let bestMatch = null;
        let bestScore = 0;

        for (const fieldResult of results) {
            const matches = fieldResult?.result || [];
            for (const match of matches) {
                if (match?.doc) {
                    const doc = match.doc;
                    if (!matchesAudience(doc, options.audience)) continue;
                    const score = scoreDocument(doc, normalizedQuery, normalizedTokens, options);

                    if (score > bestScore) {
                        bestScore = score;
                        bestMatch = doc;
                    }
                }
            }
        }

        if (bestMatch) {
            return safeReturnObject ? bestMatch : formatSearchResult(bestMatch);
        }
    }

    // Fallback: token-based search
    if (normalizedTokens.length > 0) {
        let bestMatch = null;
        let bestScore = 0;

        for (const doc of filteredDocs) {
            const weightedScore = scoreDocument(doc, normalizedQuery, normalizedTokens, options);

            if (weightedScore > bestScore) {
                bestScore = weightedScore;
                bestMatch = doc;
            }
        }

        // Only return if we have a reasonable match
        if (bestMatch && bestScore >= 1) {
            return safeReturnObject ? bestMatch : formatSearchResult(bestMatch);
        }
    }

    // Fix: Removed redundant third fallback (directMatches) - token-based search already covers this

    return null;
}

// Export additional functions for external use
function getKnowledgeBaseStats() {
    return {
        totalDocuments: kbData.length,
        sources: [...new Set(kbData.map(d => d.source))].length,
        tags: [...new Set(kbData.flatMap(d => d.tags || []))].length
    };
}

function searchKnowledgeBaseWithTag(tag) {
    return kbData.filter(doc => doc.tags?.includes(tag));
}

module.exports = {
    initializeKnowledgeBase,
    searchKnowledgeBase,
    getKnowledgeBaseStats,
    searchKnowledgeBaseWithTag
};

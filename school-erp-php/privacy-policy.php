<?php
/**
 * School ERP - Privacy Policy
 * Follows the "Academic Curator" design system
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = "Privacy Policy - Academic Curator";
require_once __DIR__ . '/includes/header.php';
?>
<style>
    /* Academic Curator Styling for public pages */
    .policy-container {
        max-width: 800px;
        margin: 2rem auto;
        padding: 2.5rem;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        border-radius: 1.5rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    }
    .policy-title {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 2.5rem;
        color: #1a1a1a;
        margin-bottom: 0.5rem;
        letter-spacing: -0.02em;
    }
    .policy-subtitle {
        color: #666;
        font-size: 1.1rem;
        margin-bottom: 2.5rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    .policy-content {
        font-size: 1.05rem;
        line-height: 1.8;
        color: #333;
    }
    .policy-content h2 {
        font-family: 'Outfit', sans-serif;
        font-size: 1.5rem;
        color: #1a1a1a;
        margin-top: 2rem;
        margin-bottom: 1rem;
    }
    .policy-content p {
        margin-bottom: 1rem;
    }
    .policy-content ul {
        margin-bottom: 1.5rem;
        padding-left: 1.5rem;
    }
    .policy-content li {
        margin-bottom: 0.5rem;
    }
</style>

<div class="policy-container">
    <h1 class="policy-title">Privacy Policy</h1>
    <p class="policy-subtitle">Last updated: <?= date('F d, Y') ?></p>
    
    <div class="policy-content">
        <h2>1. Introduction</h2>
        <p>Welcome to our School ERP system. We respect your privacy and are committed to protecting your personal data. This privacy policy will inform you as to how we look after your personal data when you visit our portal (regardless of where you visit it from) and tell you about your privacy rights and how the law protects you.</p>
        
        <h2>2. Data We Collect</h2>
        <p>We may collect, use, store and transfer different kinds of personal data about you which we have grouped together follows:</p>
        <ul>
            <li><strong>Identity Data</strong> includes first name, last name, username or similar identifier, title, date of birth and gender.</li>
            <li><strong>Contact Data</strong> includes billing address, delivery address, email address and telephone numbers.</li>
            <li><strong>Academic Data</strong> includes attendance records, examination results, assignments, and behavioral records.</li>
            <li><strong>Financial Data</strong> includes bank account and payment card details for fee processing.</li>
        </ul>
        
        <h2>3. How We Use Your Data</h2>
        <p>We will only use your personal data when the law allows us to. Most commonly, we will use your personal data in the following circumstances:</p>
        <ul>
            <li>Where we need to perform the contract we are about to enter into or have entered into with you (e.g., providing educational services).</li>
            <li>Where it is necessary for our legitimate interests (or those of a third party) and your interests and fundamental rights do not override those interests.</li>
            <li>Where we need to comply with a legal or regulatory obligation.</li>
        </ul>
        
        <h2>4. Data Security</h2>
        <p>We have put in place appropriate security measures to prevent your personal data from being accidentally lost, used or accessed in an unauthorised way, altered or disclosed. In addition, we limit access to your personal data to those employees, agents, contractors and other third parties who have a business need to know.</p>
        
        <h2>5. Your Legal Rights</h2>
        <p>Under certain circumstances, you have rights under data protection laws in relation to your personal data, including the right to request access, correction, erasure, or restriction of processing of your personal data.</p>
        
        <h2>6. Contact Us</h2>
        <p>If you have any questions about this privacy policy or our privacy practices, please contact our data privacy manager at privacy@school.edu.</p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

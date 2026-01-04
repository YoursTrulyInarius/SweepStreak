<?php
require_once 'config/database.php';
require_once 'includes/header.php';
?>

<!-- Hero Section -->
<section class="game-hero pixel-bg">
    <div class="container">
        <div class="hero-content">
            <h1 class="game-title pixel-text">SweepStreak</h1>
            <p class="game-subtitle pixel-subtitle">
                Transform classroom cleaning into an engaging game. Earn points, build streaks, and compete with classmates while keeping your school spotless.
            </p>
            
            <div class="cta-buttons">
                <a href="register.php" class="game-btn game-btn-primary pixel-btn">
                    <i class="fas fa-play"></i>
                    Start Playing
                </a>
                <a href="#how-it-works" class="game-btn game-btn-secondary pixel-btn">
                    <i class="fas fa-info-circle"></i>
                    Learn More
                </a>
            </div>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section id="how-it-works" class="game-section">
    <div class="container">
        <h2 class="section-title pixel-text">How It Works</h2>
        
        <div class="workflow-steps">
            <div class="workflow-step pixel-step">
                <div class="step-number">1</div>
                <div class="step-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <h3 class="step-title">Teacher Creates Class</h3>
                <p class="step-desc">Teacher sets up a class and gets a unique class code for students to join</p>
            </div>
            
            <div class="workflow-step pixel-step">
                <div class="step-number">2</div>
                <div class="step-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h3 class="step-title">Students Join</h3>
                <p class="step-desc">Students register with the class code and get assigned to cleaning groups</p>
            </div>
            
            <div class="workflow-step pixel-step">
                <div class="step-number">3</div>
                <div class="step-icon">
                    <i class="fas fa-broom"></i>
                </div>
                <h3 class="step-title">Complete Tasks</h3>
                <p class="step-desc">Groups complete daily cleaning tasks and submit photo evidence</p>
            </div>
            
            <div class="workflow-step pixel-step">
                <div class="step-number">4</div>
                <div class="step-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <h3 class="step-title">Earn Rewards</h3>
                <p class="step-desc">Get points, build streaks, unlock badges and climb the leaderboard</p>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="game-section features-section">
    <div class="container">
        <h2 class="section-title pixel-text">Game Features</h2>
        
        <div class="game-features">
            <div class="game-feature pixel-feature">
                <div class="feature-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <h3 class="feature-title">Points & Badges</h3>
                <p class="feature-desc">Complete cleaning tasks to earn points and unlock special achievements</p>
            </div>
            
            <div class="game-feature pixel-feature">
                <div class="feature-icon">
                    <i class="fas fa-camera"></i>
                </div>
                <h3 class="feature-title">Photo Verification</h3>
                <p class="feature-desc">Submit timestamped photos as proof of your cleaning accomplishments</p>
            </div>
            
            <div class="game-feature pixel-feature">
                <div class="feature-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="feature-title">Team Competition</h3>
                <p class="feature-desc">Compete with other classes and climb the leaderboard together</p>
            </div>
            
            <div class="game-feature pixel-feature">
                <div class="feature-icon">
                    <i class="fas fa-fire"></i>
                </div>
                <h3 class="feature-title">Streak System</h3>
                <p class="feature-desc">Maintain daily cleaning streaks for bonus points and special rewards</p>
            </div>
            
            <div class="game-feature pixel-feature">
                <div class="feature-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3 class="feature-title">Live Leaderboards</h3>
                <p class="feature-desc">Track your progress and compete with other groups in real-time</p>
            </div>
            
            <div class="game-feature pixel-feature">
                <div class="feature-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3 class="feature-title">Attendance Tracking</h3>
                <p class="feature-desc">Integrated attendance system with cleaning submissions</p>
            </div>
        </div>
    </div>
</section>

<style>
    /* Hero Section - Moved Higher */
    .game-hero {
        padding: 2rem 0 3rem;
        min-height: auto;
    }
    
    .hero-content {
        text-align: center;
        max-width: 900px;
        margin: 0 auto;
    }
    
    .game-title {
        font-size: 3.5rem;
        margin-bottom: 1.5rem;
    }
    
    .game-subtitle {
        font-size: 1.15rem;
        line-height: 1.8;
        margin-bottom: 2rem;
        max-width: 800px;
        margin-left: auto;
        margin-right: auto;
    }
    
    /* Pixel Background */
    .pixel-bg {
        background-image: 
            linear-gradient(45deg, #f0f9ff 25%, transparent 25%),
            linear-gradient(-45deg, #f0f9ff 25%, transparent 25%),
            linear-gradient(45deg, transparent 75%, #f0f9ff 75%),
            linear-gradient(-45deg, transparent 75%, #f0f9ff 75%);
        background-size: 20px 20px;
        background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
    }
    
    .pixel-text {
        font-family: 'Courier New', monospace;
        font-weight: bold;
        text-shadow: 2px 2px 0 #000;
        color: #3a86ff;
    }
    
    .pixel-subtitle {
        font-family: 'Courier New', monospace;
        color: #666;
    }
    
    .pixel-btn {
        font-family: 'Courier New', monospace;
        border: 2px solid #000;
        box-shadow: 3px 3px 0 #000;
        transition: all 0.1s ease;
        padding: 0.75rem 1.5rem;
        font-size: 1rem;
    }
    
    .pixel-btn:hover {
        transform: translate(2px, 2px);
        box-shadow: 1px 1px 0 #000;
    }
    
    /* Sections Spacing */
    .game-section {
        padding: 4rem 0;
    }
    
    .features-section {
        padding: 4rem 0 5rem;
        margin-top: 2rem;
    }
    
    .section-title {
        text-align: center;
        font-size: 2.5rem;
        margin-bottom: 3rem;
    }
    
    /* Workflow Steps */
    .pixel-step {
        border: 2px solid #000;
        background: white;
        box-shadow: 4px 4px 0 #000;
        font-family: 'Courier New', monospace;
    }
    
    .workflow-steps {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 2rem;
        margin-top: 3rem;
    }
    
    .workflow-step {
        text-align: center;
        padding: 2rem 1rem;
        position: relative;
    }
    
    .step-number {
        position: absolute;
        top: -15px;
        left: 50%;
        transform: translateX(-50%);
        background: #3a86ff;
        color: white;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        border: 2px solid #000;
    }
    
    .step-icon {
        font-size: 3rem;
        color: #3a86ff;
        margin-bottom: 1rem;
    }
    
    .step-title {
        font-size: 1.2rem;
        margin-bottom: 0.75rem;
        font-weight: bold;
    }
    
    .step-desc {
        font-size: 0.95rem;
        color: #666;
        line-height: 1.6;
    }
    
    /* Features Grid */
    .pixel-feature {
        border: 2px solid #000;
        background: white;
        box-shadow: 3px 3px 0 #000;
        font-family: 'Courier New', monospace;
        transition: all 0.2s ease;
        padding: 2rem;
        text-align: center;
    }
    
    .pixel-feature:hover {
        transform: translate(2px, 2px);
        box-shadow: 1px 1px 0 #000;
    }
    
    .game-features {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-top: 3rem;
    }
    
    .feature-icon {
        font-size: 3rem;
        color: #3a86ff;
        margin-bottom: 1rem;
    }
    
    .feature-title {
        font-size: 1.3rem;
        margin-bottom: 0.75rem;
        font-weight: bold;
    }
    
    .feature-desc {
        font-size: 0.95rem;
        color: #666;
        line-height: 1.6;
    }
    
    /* CTA Buttons */
    .cta-buttons {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 2rem;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .game-hero {
            padding: 1.5rem 0 2.5rem;
        }
        
        .game-title {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        .game-subtitle {
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }
        
        .pixel-btn {
            padding: 0.6rem 1.2rem;
            font-size: 0.9rem;
        }
        
        .section-title {
            font-size: 1.8rem;
            margin-bottom: 2rem;
        }
        
        .game-section {
            padding: 3rem 0;
        }
        
        .features-section {
            padding: 3rem 0 4rem;
            margin-top: 1.5rem;
        }
        
        .workflow-steps {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        
        .game-features {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        
        .step-icon, .feature-icon {
            font-size: 2.5rem;
        }
        
        .workflow-step, .game-feature {
            padding: 1.5rem 1rem;
        }
        
        .cta-buttons {
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .cta-buttons a {
            width: 100%;
        }
    }
    
    @media (max-width: 480px) {
        .game-hero {
            padding: 1rem 0 2rem;
        }
        
        .game-title {
            font-size: 1.5rem;
        }
        
        .game-subtitle {
            font-size: 0.85rem;
        }
        
        .section-title {
            font-size: 1.5rem;
        }
        
        .step-title, .feature-title {
            font-size: 1rem;
        }
        
        .step-desc, .feature-desc {
            font-size: 0.85rem;
        }
        
        .pixel-btn {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
    }
    
    /* Tablet Landscape */
    @media (min-width: 769px) and (max-width: 1024px) {
        .workflow-steps {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .game-features {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .game-title {
            font-size: 2.8rem;
        }
    }
    
    /* Large Desktop */
    @media (min-width: 1200px) {
        .container {
            max-width: 1140px;
        }
        
        .workflow-steps {
            gap: 2.5rem;
        }
        
        .game-features {
            gap: 2.5rem;
        }
    }
</style>
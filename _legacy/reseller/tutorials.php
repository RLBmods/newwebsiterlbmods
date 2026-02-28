<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/session.php';
require_once '../includes/get_user_info.php';
include '../db/connection.php';

requireAuth(); // Ensure user is authenticated
requireReseller();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name;?> • Tutorial</title>
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/download.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.11.2/css/all.css">
<link href="https://fonts.googleapis.com/css2?family=Oxanium:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../js/download.js" defer></script>
<script src="../js/heartbeat.js" defer></script>
<script src="../js/notify.js" defer></script>
    <style>
        .tutorial-container {
            max-width: 800px;
            margin: 60px auto;
            padding: 0 20px;
        }
        .faq-item {
            background: #1a1a1f;
            margin-bottom: 15px;
            border-radius: 10px;
            overflow: hidden;
            color: #fff;
        }
        .faq-question {
            padding: 18px 25px;
            cursor: pointer;
            font-weight: 600;
            position: relative;
            transition: background 0.3s;
        }
        .faq-question:hover {
            background: #2c2c34;
        }
        .faq-question::after {
            content: "\f107";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            position: absolute;
            right: 25px;
            top: 50%;
            transform: translateY(-50%);
            transition: transform 0.3s;
        }
        .faq-question.active::after {
            transform: translateY(-50%) rotate(180deg);
        }
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.35s ease;
            padding: 0 25px;
            background: #111;
        }
        .faq-answer p {
            padding: 15px 0;
        }
    </style>
</head>
<body>
    <!-- ========== Left Sidebar Start ========== -->
    <?php include_once('../blades/sidebar/reseller-sidebar.php'); ?>
    <!-- ========== Left Sidebar Ends ========== -->

    <main class="main-content">
        <header class="main-header">
            <div class="header-left">
                <div class="breadcrumbs">
                    <a href="/" class="breadcrumb-item"><i class="fas fa-home"></i></a>
                    <span class="breadcrumb-separator">/</span>
                    <span class="breadcrumb-current">Tutorial</span>
                </div>
            </div>

            <?php include_once('../blades/notify/notify.php'); ?>
        </header>

        <div class="content-area-wrapper">
<div class="downloads-hero gaming-hero">
    <div class="hero-overlay"></div>
    <div class="downloads-hero-content">
        <div class="downloads-icon">
            <i class="fas fa-question-circle"></i>
        </div>
        <h2>Tutorial / FAQ</h2>
        <p class="downloads-subtitle">Find answers to common reseller questions quickly and easily</p>
    </div>
</div>



            <div class="tutorial-container">
                <div class="faq-item">
                    <div class="faq-question">Do I need to disable something in BIOS?</div>
                    <div class="faq-answer">
                        <p>NO, our products work with TPM, SecureBoot, HVCI on, and up to the latest Windows 11.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">Do the cleaners work?</div>
                    <div class="faq-answer">
                        <p>Not confirmed. Due to major updates in recent anticheats and traces, we always have the cleaners in the loader when it is working and they are hidden when they aren't. Please be ready to reset if the cleaners are unavailable</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">What do I have to do to make it work?</div>
                    <div class="faq-answer">
                        <p>Disable antivirus, that's it. Our cheats modify certain game files, so antivirus programs may block them, which is why this step is required.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">What do I do if I need support?</div>
                    <div class="faq-answer">
                        <p>Deal with the reseller, not the provider. We do not provide support for your customers.</p>
                    </div>
                </div>
            </div>
        </div>

        <footer class="main-footer">
            <p>
                &copy; <?php echo date('Y'); ?> RLBMODS. All rights reserved. | 
                <span class="badge">

                </span>
            </p>
        </footer>
    </main>

    <script>
        $(document).ready(function(){
            $('.faq-question').click(function(){
                $(this).toggleClass('active');
                var answer = $(this).next('.faq-answer');
                if(answer.css('max-height') !== '0px'){
                    answer.css('max-height', '0');
                } else {
                    answer.css('max-height', answer.prop('scrollHeight') + "px");
                }
            });
        });
    </script>
</body>
</html>

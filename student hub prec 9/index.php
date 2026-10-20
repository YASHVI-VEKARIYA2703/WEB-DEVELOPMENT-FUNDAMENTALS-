<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Hub - CHARUSAT</title>
    <link rel="stylesheet" href="CSS/style.css">
</head>

<body class="index-body">

    <!-- HEADER -->
    <iframe
        src="pages/header.html"
        class="header-frame">
    </iframe>

    <!-- NOTICE BAR (NOTIFICATION BANNER) -->
    <div class="notice-bar" id="noticeBar">
        <span>📢 Latest Notice: Mid-Sem Exam Schedule Announced | Cognizance 2026 TechFest Registration Open!</span>
        <button type="button" class="close-notice-btn" onclick="document.getElementById('noticeBar').style.display='none';">&times;</button>
    </div>

    <!-- NAVIGATION -->
    <nav>
        <a href="pages/event.html">EVENTS</a>
        <a href="pages/register.php">REGISTER</a>
        <a href="pages/contact.html">CONTACT</a>
        <a href="pages/faq.html">FAQ</a>
    </nav>

    <!-- SLIDING WINDOW BACKGROUND ARROW CONTROLS -->
    <button type="button" class="bg-arrow left-arrow" id="prevBgBtn" onclick="changeBackground(-1)" title="Previous Background Image">&#10094;</button>
    <button type="button" class="bg-arrow right-arrow" id="nextBgBtn" onclick="changeBackground(1)" title="Next Background Image">&#10095;</button>

    <!-- BACKGROUND SLIDE TITLE & INDICATOR -->
    <div class="bg-indicator" id="bgIndicator">
        CHARUSAT Main Campus (1 / 4)
    </div>

    <!-- HOME LINK AT BOTTOM RIGHT -->
    <a href="index.html" class="home-btn">🏠 Home</a>
    <button id="themeButton" class="themeButton">🌙 Dark Mode</button>

    <!-- FOOTER -->
    <footer>
        &copy; 2026 STUDENT HUB • CHARUSAT University • All Rights Reserved.
    </footer>

    <!-- BEGINNER-STYLE CSS FOR BACKGROUND SLIDER & NOTICE -->
    <style>
        /* Dismissible Notification Banner */
        .notice-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 10px 45px;
        }

        .close-notice-btn {
            position: absolute;
            right: 15px;
            background: transparent;
            border: none;
            font-size: 22px;
            font-weight: bold;
            color: #1B1F44;
            cursor: pointer;
            line-height: 1;
        }

        .close-notice-btn:hover {
            color: darkred;
        }

        /* Sliding Background Arrow Controls */
        .bg-arrow {
            position: fixed;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(27, 31, 68, 0.75);
            color: white;
            border: 2px solid orange;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            font-size: 22px;
            cursor: pointer;
            z-index: 1000;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            user-select: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
        }

        .bg-arrow:hover {
            background-color: orange;
            color: #1B1F44;
            transform: translateY(-50%) scale(1.1);
        }

        .left-arrow {
            left: 20px;
        }

        .right-arrow {
            right: 20px;
        }

        /* Background Slide Indicator Badge */
        .bg-indicator {
            position: fixed;
            bottom: 25px;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(27, 31, 68, 0.85);
            color: #ffd700;
            padding: 8px 20px;
            border-radius: 20px;
            border: 1px solid orange;
            font-size: 13px;
            font-weight: bold;
            z-index: 999;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.35);
            letter-spacing: 0.5px;
            user-select: none;
        }

        /* Responsive adjustment for small screens */
        @media (max-width: 600px) {
            .bg-arrow {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }
            .left-arrow {
                left: 10px;
            }
            .right-arrow {
                right: 10px;
            }
        }
    </style>

    <!-- BEGINNER-STYLE JAVASCRIPT FOR BACKGROUND SLIDER -->
    <script>
        // Array of campus background images
        var bgImages = [
            "images/building.jpg",
            "images/campus.jpg",
            "images/library.jpg",
            "images/campus_life.jpg",
            "images/lab.jpg"
        ];

        // Matching descriptive titles
        var bgTitles = [
            "CHARUSAT Main Campus",
            "CHARUSAT Green Campus & Architecture",
            "Central University Library & Study Commons",
            "Vibrant Campus Life & Student Community",
            "Advanced Computing & Innovation Laboratories"
        ];

        var currentBgIndex = 0;

        function changeBackground(direction) {
            currentBgIndex = currentBgIndex + direction;

            // Loop back if reaching end or start
            if (currentBgIndex >= bgImages.length) {
                currentBgIndex = 0;
            }
            if (currentBgIndex < 0) {
                currentBgIndex = bgImages.length - 1;
            }

            // Change body background image
            document.body.style.backgroundImage = "url('" + bgImages[currentBgIndex] + "')";

            // Update bottom indicator badge
            var indicator = document.getElementById("bgIndicator");
            if (indicator) {
                indicator.textContent = bgTitles[currentBgIndex] + " (" + (currentBgIndex + 1) + " / " + bgImages.length + ")";
            }
        }

        // Support keyboard left and right arrow keys
        window.addEventListener("keydown", function (e) {
            if (e.key === "ArrowLeft") {
                changeBackground(-1);
            } else if (e.key === "ArrowRight") {
                changeBackground(1);
            }
        });
    </script>
    <script src="js/script.js"></script>

</body>

</html>
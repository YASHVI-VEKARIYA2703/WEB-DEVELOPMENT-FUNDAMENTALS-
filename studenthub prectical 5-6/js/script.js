// ==========================================================================
// STUDENT HUB - JAVASCRIPT
// Form Validation, User Registration, Profile Display & Events (Fetch API)
// ==========================================================================


// ==========================================================================
// 1. STUDENT REGISTRATION FORM VALIDATION & SAVING DETAILS
// ==========================================================================

document.addEventListener('DOMContentLoaded', function () {

    const registerForm = document.getElementById('registerForm');
    if (!registerForm) return;

    // Get input elements
    const fullnameInput = document.getElementById('fullname');
    const enrollmentInput = document.getElementById('enrollment');
    const emailInput = document.getElementById('email');
    const mobileInput = document.getElementById('mobile');
    const departmentInput = document.getElementById('department');
    const semesterInput = document.getElementById('semester');
    const passwordInput = document.getElementById('password');
    const cpasswordInput = document.getElementById('cpassword');
    const successBanner = document.getElementById('successBanner');

    // Helper function to show error message
    function showError(input, errorElementId, message) {
        const errorElement = document.getElementById(errorElementId);
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.classList.add('visible');
        }
        if (input) {
            input.classList.add('input-error');
            input.classList.remove('input-valid');
        }
    }

    // Helper function to clear error message
    function clearError(input, errorElementId) {
        const errorElement = document.getElementById(errorElementId);
        if (errorElement) {
            errorElement.textContent = '';
            errorElement.classList.remove('visible');
        }
        if (input) {
            input.classList.remove('input-error');
            input.classList.add('input-valid');
        }
    }

    // Reset input style
    function resetInputState(input, errorElementId) {
        const errorElement = document.getElementById(errorElementId);
        if (errorElement) {
            errorElement.textContent = '';
            errorElement.classList.remove('visible');
        }
        if (input) {
            input.classList.remove('input-error');
            input.classList.remove('input-valid');
        }
    }

    // 1. Full Name Validation: Name should not contain numbers
    function validateFullName() {
        const val = fullnameInput.value.trim();
        if (!val) {
            showError(fullnameInput, 'fullname-error', 'Full Name is required.');
            return false;
        }
        if (/\d/.test(val)) {
            showError(fullnameInput, 'fullname-error', 'Name should not contain numbers.');
            return false;
        }
        if (!/^[A-Za-z\s.'-]+$/.test(val)) {
            showError(fullnameInput, 'fullname-error', 'Name should only contain letters and spaces.');
            return false;
        }
        clearError(fullnameInput, 'fullname-error');
        return true;
    }

    // 2. Enrollment Number Validation: Must not be empty
    function validateEnrollment() {
        const val = enrollmentInput.value.trim();
        if (!val) {
            showError(enrollmentInput, 'enrollment-error', 'Enrollment Number is required.');
            return false;
        }
        clearError(enrollmentInput, 'enrollment-error');
        return true;
    }

    // 3. Email Validation: Must be valid email format
    function validateEmail() {
        const val = emailInput.value.trim();
        if (!val) {
            showError(emailInput, 'email-error', 'Email Address is required.');
            return false;
        }
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(val)) {
            showError(emailInput, 'email-error', 'Please enter a valid email address.');
            return false;
        }
        clearError(emailInput, 'email-error');
        return true;
    }

    // 4. Mobile Number Validation: 10-digit number
    function validateMobile() {
        const val = mobileInput.value.trim();
        if (!val) {
            showError(mobileInput, 'mobile-error', 'Mobile Number is required.');
            return false;
        }
        const mobileRegex = /^[0-9]{10}$/;
        if (!mobileRegex.test(val)) {
            showError(mobileInput, 'mobile-error', 'Please enter a valid 10-digit mobile number.');
            return false;
        }
        clearError(mobileInput, 'mobile-error');
        return true;
    }

    // 5. Password Validation: At least 6 characters
    function validatePassword() {
        const val = passwordInput.value;
        if (!val) {
            showError(passwordInput, 'password-error', 'Password is required.');
            return false;
        }
        if (val.length < 6) {
            showError(passwordInput, 'password-error', 'Password must contain at least 6 characters.');
            return false;
        }
        clearError(passwordInput, 'password-error');
        return true;
    }

    // 6. Confirm Password Validation: Must match password
    function validateConfirmPassword() {
        const val = cpasswordInput.value;
        if (!val) {
            showError(cpasswordInput, 'cpassword-error', 'Please confirm your password.');
            return false;
        }
        if (val !== passwordInput.value) {
            showError(cpasswordInput, 'cpassword-error', 'Passwords do not match.');
            return false;
        }
        clearError(cpasswordInput, 'cpassword-error');
        return true;
    }

    // Real-time input listeners
    fullnameInput.addEventListener('input', validateFullName);
    enrollmentInput.addEventListener('input', validateEnrollment);
    emailInput.addEventListener('input', validateEmail);
    mobileInput.addEventListener('input', validateMobile);

    passwordInput.addEventListener('input', function () {
        validatePassword();
        if (cpasswordInput.value.length > 0) {
            validateConfirmPassword();
        }
    });

    cpasswordInput.addEventListener('input', validateConfirmPassword);

    // Form Reset Event
    registerForm.addEventListener('reset', function () {
        setTimeout(function () {
            resetInputState(fullnameInput, 'fullname-error');
            resetInputState(enrollmentInput, 'enrollment-error');
            resetInputState(emailInput, 'email-error');
            resetInputState(mobileInput, 'mobile-error');
            resetInputState(passwordInput, 'password-error');
            resetInputState(cpasswordInput, 'cpassword-error');
            if (successBanner) {
                successBanner.classList.remove('visible');
            }
        }, 20);
    });

    // Form Submit Event
    registerForm.addEventListener('submit', function (e) {
        e.preventDefault();

        // Check all validations
        const isNameValid = validateFullName();
        const isEnrollValid = validateEnrollment();
        const isEmailValid = validateEmail();
        const isMobileValid = validateMobile();
        const isPassValid = validatePassword();
        const isCPassValid = validateConfirmPassword();

        const isFormValid = isNameValid && isEnrollValid && isEmailValid && isMobileValid && isPassValid && isCPassValid;

        if (isFormValid) {

            // Save new user details in localStorage
            const studentData = {
                fullname: fullnameInput.value.trim(),
                enrollment: enrollmentInput.value.trim(),
                email: emailInput.value.trim(),
                mobile: mobileInput.value.trim(),
                department: departmentInput ? departmentInput.value : "Computer Engineering",
                semester: semesterInput ? semesterInput.value : "Semester 3"
            };

            localStorage.setItem('studentProfile', JSON.stringify(studentData));

            // Show success banner
            if (successBanner) {
                successBanner.textContent = 'Registration successful! Showing your entered details in your profile...';
                successBanner.classList.add('visible');
            }

            // Redirect to profile page after 1.2 seconds so new user sees their entered details
            setTimeout(function () {
                window.location.href = "profile.html";
            }, 1200);

        } else {
            // Scroll to the first error input
            const firstErrorInput = registerForm.querySelector('.input-error');
            if (firstErrorInput) {
                firstErrorInput.focus();
            }
        }
    });

});


// ==========================================================================
// 2. DISPLAY PROFILE (SHOW REGISTERED USER DETAILS OR FETCH FROM JSON)
// ==========================================================================

document.addEventListener("DOMContentLoaded", function () {

    const profileName = document.getElementById("profileName");
    // If not on profile page, skip
    if (!profileName) return;

    const profileEnrollment = document.getElementById("profileEnrollment");
    const profileEmail = document.getElementById("profileEmail");
    const profileMobile = document.getElementById("profileMobile");
    const profileDegree = document.getElementById("profileDegree");
    const profileSemester = document.getElementById("profileSemester");
    const profileStatusMsg = document.getElementById("profileStatusMsg");

    // Check if new user registered and data is saved in localStorage
    const savedData = localStorage.getItem("studentProfile");

    if (savedData) {
        // User registered! Load their entered details
        const student = JSON.parse(savedData);

        if (profileName) profileName.textContent = student.fullname;
        if (profileEnrollment) profileEnrollment.textContent = student.enrollment;
        if (profileEmail) profileEmail.textContent = student.email;
        if (profileMobile) profileMobile.textContent = student.mobile;
        if (profileDegree) profileDegree.textContent = "B.Tech " + student.department;
        if (profileSemester) profileSemester.textContent = student.semester;

        if (profileStatusMsg) {
            profileStatusMsg.style.display = "block";
            profileStatusMsg.textContent = "Welcome " + student.fullname + "! Showing your registered profile details.";
        }

    } else {
        // No registered user yet: Fetch default profile using Fetch API from external JSON file
        fetch("../DATA/profile.json")
            .then(function (response) {
                if (!response.ok) {
                    throw new Error("profile.json could not be loaded");
                }
                return response.json();
            })
            .then(function (data) {
                // Populate default profile from external JSON
                if (profileName) profileName.textContent = data.fullname;
                if (profileEnrollment) profileEnrollment.textContent = data.enrollment;
                if (profileEmail) profileEmail.textContent = data.email;
                if (profileMobile) profileMobile.textContent = data.mobile;
                if (profileDegree) profileDegree.textContent = data.degree;
                if (profileSemester) profileSemester.textContent = data.semester;
            })
            .catch(function (error) {
                console.log("Could not load external profile JSON, using default page values.", error);
            });
    }

});


// ==========================================================================
// 3. DASHBOARD STUDENT INFO UPDATE
// ==========================================================================

document.addEventListener("DOMContentLoaded", function () {

    const dashStudentName = document.getElementById("dashStudentName");
    const dashStudentProgram = document.getElementById("dashStudentProgram");

    if (!dashStudentName && !dashStudentProgram) return;

    // If a user has registered, update the dashboard bar with their details
    const savedData = localStorage.getItem("studentProfile");

    if (savedData) {
        const student = JSON.parse(savedData);
        if (dashStudentName) {
            dashStudentName.textContent = student.fullname + " (" + student.enrollment + ")";
        }
        if (dashStudentProgram) {
            dashStudentProgram.textContent = "B.Tech (" + student.department + ") - " + student.semester;
        }
    }

});


// ==========================================================================
// 4. LIGHT / DARK MODE FOR ALL PAGES
// ==========================================================================

const themeButton = document.getElementById("themeButton");

// Apply saved theme when page loads
const savedTheme = localStorage.getItem("theme");

if (savedTheme === "dark") {
    document.body.classList.add("dark-mode");
}

function updateThemeButton() {
    if (!themeButton) return;
    if (document.body.classList.contains("dark-mode")) {
        themeButton.innerHTML = "☀️ Light Mode";
    } else {
        themeButton.innerHTML = "🌙 Dark Mode";
    }
}

updateThemeButton();

if (themeButton) {
    themeButton.addEventListener("click", function () {
        document.body.classList.toggle("dark-mode");
        if (document.body.classList.contains("dark-mode")) {
            localStorage.setItem("theme", "dark");
        } else {
            localStorage.setItem("theme", "light");
        }
        updateThemeButton();
    });
}


// ==========================================================================
// 5. STUDENT HUB - EVENTS
// Fetch JSON + Dynamic Rendering + Search + Filter + Sort + Pagination
// ==========================================================================

document.addEventListener("DOMContentLoaded", function () {

    // Get HTML Elements
    const eventsList = document.getElementById("eventsList");
    const searchInput = document.getElementById("searchInput");
    const categoryFilter = document.getElementById("categoryFilter");
    const sortEvents = document.getElementById("sortEvents");
    const pagination = document.getElementById("pagination");

    // If not on events page, exit
    if (!eventsList) return;

    // Fallback data in case opened with file:// without local server
    const fallbackEvents = [
        {
            "id": 1,
            "tag": "Technical Fest",
            "title": "Cognizance 2026 - Tech Fest & Hackathon",
            "date": "September 25 - 26, 2026",
            "venue": "Central Auditorium & IT Labs",
            "organizer": "Computer Engineering",
            "description": "A 36-hour coding hackathon featuring AI/ML challenges, Web development contests, Code Relay, and Bug Hunting competitions.",
            "button": "Register for Event",
            "link": "register.html"
        },
        {
            "id": 2,
            "tag": "Cultural Fest",
            "title": "Spoural 2026 - Youth Cultural Festival",
            "date": "October 12 - 14, 2026",
            "venue": "Open Air Theatre",
            "organizer": "Student Activity Council",
            "description": "Annual cultural fest featuring dance competitions, drama, music band battles, fashion shows, and celebrity musical night.",
            "button": "Join Cultural Fest",
            "link": "register.html"
        },
        {
            "id": 3,
            "tag": "Workshop",
            "title": "TechConclave: AI & Cloud Computing Workshop",
            "date": "November 05, 2026",
            "venue": "Seminar Hall 1, CE Block",
            "organizer": "Industry Experts",
            "description": "Hands-on practical session covering Artificial Intelligence basics, Cloud Deployment, and career roadmaps for students.",
            "button": "Reserve Seat",
            "link": "register.html"
        },
        {
            "id": 4,
            "tag": "Sports Meet",
            "title": "CHARUSAT Premier League & Sports Meet",
            "date": "December 18 - 22, 2026",
            "venue": "Sports Ground",
            "organizer": "Sports Department",
            "description": "Inter-department tournament for Cricket, Football, Volleyball, Badminton, Table Tennis, and Chess.",
            "button": "Register Team",
            "link": "register.html"
        },
        {
            "id": 5,
            "tag": "Academic",
            "title": "15th Annual Convocation Ceremony",
            "date": "January 10, 2027",
            "venue": "Grand Convention Hall",
            "organizer": "Graduating Batches",
            "description": "Awarding of degrees and academic Gold Medals to graduating B.Tech, M.Tech, and Ph.D. students.",
            "button": "Contact Helpdesk",
            "link": "contact.html"
        },
        {
            "id": 6,
            "tag": "Workshop",
            "title": "Web Development Bootcamp",
            "date": "January 20 - 22, 2027",
            "venue": "Computer Lab 2",
            "organizer": "IT Department",
            "description": "A beginner-friendly workshop covering HTML, CSS, JavaScript and basic web development projects.",
            "button": "Register Now",
            "link": "register.html"
        },
        {
            "id": 7,
            "tag": "Technical Fest",
            "title": "CodeSprint Programming Contest",
            "date": "February 05, 2027",
            "venue": "Programming Lab",
            "organizer": "Computer Engineering",
            "description": "Programming competition focused on problem solving, data structures and algorithmic thinking.",
            "button": "Register Now",
            "link": "register.html"
        },
        {
            "id": 8,
            "tag": "Sports Meet",
            "title": "CHARUSAT Badminton Championship",
            "date": "February 18 - 19, 2027",
            "venue": "Indoor Sports Complex",
            "organizer": "Sports Department",
            "description": "University-level badminton championship with singles and doubles categories.",
            "button": "Register Team",
            "link": "register.html"
        }
    ];

    // Variables
    let allEvents = [];
    let filteredEvents = [];
    let currentPage = 1;
    const eventsPerPage = 4;

    // Fetch data from external JSON file using Fetch API
    fetch("../DATA/event.json")
        .then(function (response) {
            if (!response.ok) {
                // Try alternate path if needed
                return fetch("../DATA/events.json").then(function (res) {
                    if (!res.ok) throw new Error("Could not find event JSON file");
                    return res.json();
                });
            }
            return response.json();
        })
        .then(function (data) {
            // Save fetched events
            allEvents = data;
            filteredEvents = allEvents;
            displayEvents();
        })
        .catch(function (error) {
            console.log("Fetch failed, using local fallback data:", error);
            // Fallback for file:// browser restrictions
            allEvents = fallbackEvents;
            filteredEvents = allEvents;
            displayEvents();
        });


    // ========================================================
    // DYNAMIC RENDERING OF EVENTS
    // ========================================================

    function displayEvents() {

        // Clear current events
        eventsList.innerHTML = "";

        // Calculate starting and ending index for pagination
        const startIndex = (currentPage - 1) * eventsPerPage;
        const endIndex = startIndex + eventsPerPage;

        // Get events for current page
        const eventsToDisplay = filteredEvents.slice(startIndex, endIndex);

        // If no events found
        if (eventsToDisplay.length === 0) {
            eventsList.innerHTML = "<p class='no-events'>No events found.</p>";
            if (pagination) pagination.innerHTML = "";
            return;
        }

        // Create HTML cards for each event dynamically
        eventsToDisplay.forEach(function (event) {

            const eventItem = document.createElement("div");
            eventItem.className = "event-item";

            eventItem.innerHTML = `
                <span class="event-tag">
                    ${event.tag}
                </span>

                <h2>
                    ${event.title}
                </h2>

                <div class="event-details">
                    <b>Date:</b> ${event.date}
                    <span>|</span>
                    <b>Venue:</b> ${event.venue}
                    <span>|</span>
                    <b>Organizer:</b> ${event.organizer}
                </div>

                <p>
                    ${event.description}
                </p>

                <a href="${event.link}" class="event-btn">
                    ${event.button}
                </a>
            `;

            eventsList.appendChild(eventItem);
        });

        // Update pagination buttons
        createPagination();
    }


    // ========================================================
    // SEARCH & FILTER & SORT EVENT LISTENERS
    // ========================================================

    if (searchInput) {
        searchInput.addEventListener("input", function () {
            currentPage = 1; // Reset to page 1
            applyFilters();
        });
    }

    if (categoryFilter) {
        categoryFilter.addEventListener("change", function () {
            currentPage = 1; // Reset to page 1
            applyFilters();
        });
    }

    if (sortEvents) {
        sortEvents.addEventListener("change", function () {
            applyFilters();
        });
    }


    // ========================================================
    // APPLY SEARCH, FILTER, AND SORTING
    // ========================================================

    function applyFilters() {

        const searchText = searchInput ? searchInput.value.toLowerCase().trim() : "";
        const selectedCategory = categoryFilter ? categoryFilter.value : "all";

        // Filter events
        filteredEvents = allEvents.filter(function (event) {

            // Search matches title, description, tag, or venue
            const titleMatch = event.title.toLowerCase().includes(searchText);
            const descriptionMatch = event.description.toLowerCase().includes(searchText);
            const tagMatch = event.tag.toLowerCase().includes(searchText);
            const venueMatch = event.venue.toLowerCase().includes(searchText);

            const matchesSearch = titleMatch || descriptionMatch || tagMatch || venueMatch;

            // Category match
            const matchesCategory = (selectedCategory === "all" || event.tag === selectedCategory);

            return matchesSearch && matchesCategory;
        });

        // Sort events
        if (sortEvents) {
            if (sortEvents.value === "titleAsc") {
                filteredEvents.sort(function (a, b) {
                    return a.title.localeCompare(b.title);
                });
            } else if (sortEvents.value === "titleDesc") {
                filteredEvents.sort(function (a, b) {
                    return b.title.localeCompare(a.title);
                });
            }
        }

        // Display the filtered & sorted events
        displayEvents();
    }


    // ========================================================
    // PAGINATION
    // ========================================================

    function createPagination() {

        if (!pagination) return;

        // Clear existing buttons
        pagination.innerHTML = "";

        // Calculate total pages
        const totalPages = Math.ceil(filteredEvents.length / eventsPerPage);

        // If only 1 page or none, don't show pagination
        if (totalPages <= 1) {
            return;
        }

        // PREVIOUS BUTTON
        const previousButton = document.createElement("button");
        previousButton.innerText = "Previous";
        previousButton.disabled = (currentPage === 1);

        previousButton.addEventListener("click", function () {
            if (currentPage > 1) {
                currentPage--;
                displayEvents();
            }
        });

        pagination.appendChild(previousButton);

        // NUMBERED BUTTONS (1, 2, 3...)
        for (let i = 1; i <= totalPages; i++) {
            const pageButton = document.createElement("button");
            pageButton.innerText = i;

            if (i === currentPage) {
                pageButton.classList.add("active");
            }

            pageButton.addEventListener("click", function () {
                currentPage = i;
                displayEvents();
            });

            pagination.appendChild(pageButton);
        }

        // NEXT BUTTON
        const nextButton = document.createElement("button");
        nextButton.innerText = "Next";
        nextButton.disabled = (currentPage === totalPages);

        nextButton.addEventListener("click", function () {
            if (currentPage < totalPages) {
                currentPage++;
                displayEvents();
            }
        });

        pagination.appendChild(nextButton);
    }

});
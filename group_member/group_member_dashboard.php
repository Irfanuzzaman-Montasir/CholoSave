<?php
session_start();

if (!isset($_SESSION['group_id'])) {
    header("Location: /test_project/error_page.php"); // Redirect if group_id is not set
    exit;
}

$group_id = $_SESSION['group_id'];
$user_id = $_SESSION['user_id'];
if (isset($_SESSION['group_id']) && isset($_SESSION['user_id'])) {
    $group_id = $_SESSION['group_id'];
    $user_id = $_SESSION['user_id'];
    echo 'This is group id: ' . htmlspecialchars($group_id, ENT_QUOTES, 'UTF-8');
    echo 'This is user id: ' . htmlspecialchars($user_id, ENT_QUOTES, 'UTF-8');
} else {
    echo 'Group ID is not set in the session.';
}

if (!isset($conn)) {
    include 'db.php'; // Ensure database connection
}

// Queries
$total_group_savings_query = "SELECT IFNULL(SUM(amount), 0) AS total_group_savings FROM savings WHERE group_id = ?";
$month_savings_query = "SELECT IFNULL(SUM(amount), 0) AS this_month_savings FROM savings WHERE group_id = ? AND MONTH(created_at) = MONTH(CURRENT_DATE)";
$total_members_query = "SELECT COUNT(*) AS total_members FROM group_membership WHERE group_id = ? AND status = 'approved'";
$new_members_query = "SELECT COUNT(*) AS new_members FROM group_membership WHERE group_id = ? AND status = 'approved' AND MONTH(join_date) = MONTH(CURRENT_DATE)";
$emergency_query = "SELECT emergency_fund FROM my_group WHERE group_id = ?";

// Fetch Data
function fetchSingleValue($conn, $query, $param)
{
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $param);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return array_values($row)[0]; // Return the first value
}

$total_group_savings = fetchSingleValue($conn, $total_group_savings_query, $group_id);
echo "Total Group Savings: $total_group_savings"; // Debug output

$this_month_savings = fetchSingleValue($conn, $month_savings_query, $group_id);
echo "This Month's Savings: $this_month_savings"; // Debug output

$total_members = fetchSingleValue($conn, $total_members_query, $group_id);
echo "Total Members: $total_members"; // Debug output

$new_members = fetchSingleValue($conn, $new_members_query, $group_id);
echo "New Members This Month: $new_members"; // Debug output

$emergency_fund = fetchSingleValue($conn, $emergency_query, $group_id);
echo "Emergency Fund: $emergency_fund"; // Debug output


//Graph or chart showing code //
// Get last 4 months of savings data
$query = "
    SELECT 
        DATE_FORMAT(created_at, '%b') as month,
        SUM(amount) as total_amount
    FROM savings 
    WHERE group_id = ? 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 4 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY created_at DESC
    LIMIT 4
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $group_id);
$stmt->execute();
$result = $stmt->get_result();

// Store the data in an array
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

// Reverse array to show oldest to newest
$data = array_reverse($data);

// Find the maximum amount for scaling
$max_amount = 0;
foreach ($data as $row) {
    $max_amount = max($max_amount, $row['total_amount']);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced CholoSave Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" type="text/css" href="group_member_dashboard_style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        .custom-font {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-100 dark-mode-transition">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Bar -->
            <header class="flex items-center justify-between p-4 bg-white shadow dark-mode-transition">
                <div class="flex items-center justify-center w-full">
                    <button id="menu-button"
                        class="md:hidden p-2 hover:bg-gray-100 rounded-lg transition-colors duration-200 absolute left-2">
                        <i class="fa-solid fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-5xl font-semibold custom-font">
                        <i class="fa-solid fa-money-bill-transfer mr-3"></i>
                        Dashboard
                    </h1>
                </div>
                <!-- <div class="flex items-center space-x-4">
                    <button class="p-2 hover:bg-gray-100 rounded-full transition-colors duration-200">
                        <i class="fas fa-bell"></i>
                    </button>
                    <button class="p-2 hover:bg-gray-100 rounded-full transition-colors duration-200">
                        <i class="fas fa-user-circle"></i>
                    </button>
                </div> -->
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto p-4">
                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="stats-card bg-white p-6 rounded-lg shadow cursor-pointer">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-gray-500">Total Savings</h3>
                                <p class="text-2xl font-bold" id="savings-counter">
                                    $<?php echo number_format($total_group_savings, 2); ?></p>
                                <p class="text-green-500 text-sm">+$<?php echo number_format($this_month_savings, 2); ?>
                                    this month</p>
                            </div>
                            <div class="text-2xl text-gray-400">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card bg-white p-6 rounded-lg shadow cursor-pointer">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-gray-500">Members</h3>
                                <p class="text-2xl font-bold" id="members-counter"><?php echo $total_members; ?></p>
                                <p class="text-green-500 text-sm">+<?php echo $new_members; ?> new this month</p>
                            </div>
                            <div class="text-2xl text-gray-400">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card bg-white p-6 rounded-lg shadow cursor-pointer">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-gray-500">Emergency Fund</h3>
                                <p class="text-2xl font-bold" id="fund-counter">
                                    $<?php echo number_format($emergency_fund, 2); ?></p>
                            </div>
                            <div class="text-2xl text-gray-400">
                                <i class="fas fa-piggy-bank"></i>
                            </div>
                        </div>
                    </div>
                </div>

<!-- Graph or chart showing code -->
                <div class="h-96 w-1/2 p-8 bg-white rounded-lg shadow-lg">
                    <h2 class="text-xl font-bold mb-6 text-gray-800">Monthly Savings</h2>

                    <div class="flex items-end h-64 space-x-6 mb-4">
                        <?php foreach ($data as $month_data): ?>
                            <?php
                            // Calculate height percentage
                            $height_percentage = ($month_data['total_amount'] / $max_amount) * 100;
                            ?>
                            <div class="flex flex-col items-center flex-1">
                                <div class="w-full bg-blue-500 hover:bg-blue-600 rounded-t-lg transition-all duration-300"
                                    style="height: <?php echo $height_percentage; ?>%;">
                                    <div class="text-white text-center -mt-6">
                                        $<?php echo number_format($month_data['total_amount'], 2); ?>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-600 mt-2">
                                    <?php echo $month_data['month']; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Y-axis labels -->
                    <div class="flex justify-between text-sm text-gray-600 px-2">
                        <?php
                        $steps = 5;
                        for ($i = 0; $i <= $steps; $i++) {
                            $value = ($max_amount / $steps) * $i;
                            echo "<div class='w-full border-t border-gray-200 pt-2 text-center'>$" . number_format($value, 0) . "</div>";
                        }
                        ?>
                    </div>
                </div>

                <!-- Polls Section -->
                <?php include 'polls.php'; ?>

            </main>
        </div>
    </div>



    <script>
        // Counter animation function
        function animateCounter(element, target, duration = 2000, prefix = '') {
            let start = 0;
            const increment = target / (duration / 16);
            const animate = () => {
                start += increment;
                if (start < target) {
                    element.textContent = prefix + Math.floor(start).toLocaleString();
                    requestAnimationFrame(animate);
                } else {
                    element.textContent = prefix + target.toLocaleString();
                }
            };
            animate();
        }

        // Initialize animations
        document.addEventListener('DOMContentLoaded', () => {
            // PHP values dynamically passed to JavaScript
            const totalSavings = <?php echo json_encode($total_group_savings); ?>;
            const totalMembers = <?php echo json_encode($total_members); ?>;
            const emergencyFund = <?php echo json_encode($emergency_fund); ?>;

            // Animate counters
            animateCounter(document.getElementById('savings-counter'), totalSavings, 2000, '$');
            animateCounter(document.getElementById('members-counter'), totalMembers);
            animateCounter(document.getElementById('fund-counter'), emergencyFund, 2000, '$');

            // Animate poll bars
            document.querySelectorAll('.poll-option').forEach(option => {
                const bar = option.querySelector('.bg-blue-500');
                const percentage = option.querySelector('.text-gray-500').textContent;
                setTimeout(() => {
                    bar.style.width = percentage;
                }, 500);
            });
        });



        // Dark mode functionality
        let isDarkMode = localStorage.getItem('darkMode') === 'true';
        const body = document.body;
        const themeToggle = document.getElementById('theme-toggle');
        const themeIcon = themeToggle.querySelector('i');
        const themeText = themeToggle.querySelector('span');

        function updateTheme() {
            if (isDarkMode) {
                body.classList.add('dark-mode');
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
                themeText.textContent = 'Light Mode';
            } else {
                body.classList.remove('dark-mode');
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
                themeText.textContent = 'Dark Mode';
            }
        }

        // Initialize theme
        updateTheme();

        themeToggle.addEventListener('click', () => {
            isDarkMode = !isDarkMode;
            localStorage.setItem('darkMode', isDarkMode);
            updateTheme();
        });


        window.addEventListener('resize', handleResize);
        handleResize();

        // Add smooth scroll behavior
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>

</html>
<?php
require_once 'db_config.php';

echo "Setting up skills and employee_skills tables...\n";

// 1. Create skills table
$createSkills = "CREATE TABLE IF NOT EXISTS skills (
    skill_id INT AUTO_INCREMENT PRIMARY KEY,
    skill_name VARCHAR(100) NOT NULL UNIQUE
)";
if ($conn->query($createSkills)) {
    echo "Skills table created or already exists.\n";
} else {
    echo "Error creating skills table: " . $conn->error . "\n";
}

// 2. Create employee_skills table
$createEmpSkills = "CREATE TABLE IF NOT EXISTS employee_skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    skill_id INT NOT NULL,
    proficiency_level INT NOT NULL CHECK(proficiency_level BETWEEN 1 AND 5),
    UNIQUE KEY emp_skill_unique (user_id, skill_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(skill_id) ON DELETE CASCADE
)";
if ($conn->query($createEmpSkills)) {
    echo "Employee_skills table created or already exists.\n";
} else {
    echo "Error creating employee_skills table: " . $conn->error . "\n";
}

// 3. Seed skills
$dummy_skills = [
    'PHP Development', 'React Frontend', 'UI/UX Design', 
    'Marketing Strategy', 'Data Analysis', 'Project Management',
    'Customer Support', 'SEO Optimization', 'Database Administration'
];

foreach ($dummy_skills as $skill) {
    $stmt = $conn->prepare("INSERT IGNORE INTO skills (skill_name) VALUES (?)");
    $stmt->bind_param("s", $skill);
    $stmt->execute();
}
echo "Seeded skills.\n";

// 4. Seed random proficiencies for existing employees
$emp_res = $conn->query("SELECT user_id FROM users WHERE role = 'Employee'");
$skill_res = $conn->query("SELECT skill_id FROM skills");
$all_skills = [];
while ($row = $skill_res->fetch_assoc()) {
    $all_skills[] = $row['skill_id'];
}

if (count($all_skills) > 0) {
    while ($emp = $emp_res->fetch_assoc()) {
        $uid = $emp['user_id'];
        
        // Give each employee 2 to 4 random skills
        $num_skills = rand(2, 4);
        $assigned = (array) array_rand(array_flip($all_skills), $num_skills);
        
        foreach ($assigned as $sid) {
            $prof = rand(2, 5); // Random proficiency between 2 and 5
            $stmt = $conn->prepare("INSERT IGNORE INTO employee_skills (user_id, skill_id, proficiency_level) VALUES (?, ?, ?)");
            $stmt->bind_param("sii", $uid, $sid, $prof);
            $stmt->execute();
        }
    }
    echo "Assigned random skills and proficiencies to employees.\n";
}

echo "Database setup complete!\n";
?>

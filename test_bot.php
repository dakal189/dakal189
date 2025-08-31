<?php
// Simple test to check if bot loads without errors
echo "Testing bot file...\n";

// Include the bot file
try {
    include_once 'complete_bot.php';
    echo "✅ Bot file loaded successfully!\n";
} catch (Exception $e) {
    echo "❌ Error loading bot file: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "❌ Fatal error loading bot file: " . $e->getMessage() . "\n";
}

echo "Test completed.\n";
?>
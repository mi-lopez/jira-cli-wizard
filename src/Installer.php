<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard;

class Installer
{
    public static function postInstall(): void
    {
        echo "\n";
        echo "🎯 Jira CLI Wizard installed successfully!\n";
        echo "=====================================\n\n";

        // Make binary executable
        $binPath = __DIR__ . '/../bin/jira-wizard';
        if (file_exists($binPath)) {
            chmod($binPath, 0755);
            echo "✅ Binary permissions set correctly\n\n";
        }

        echo "📋 Next steps:\n";
        echo "1. Configure your Jira credentials:\n";
        echo "   ./vendor/bin/jira-wizard configure\n\n";
        echo "2. Create your first ticket:\n";
        echo "   ./vendor/bin/jira-wizard create\n\n";

        echo "💡 Need help?\n";
        echo "   ./vendor/bin/jira-wizard --help\n";
        echo "   ./vendor/bin/jira-wizard status\n\n";

        echo "🔗 API Token: https://id.atlassian.com/manage-profile/security/api-tokens\n\n";

        echo "Happy ticket creating! 🚀\n\n";
    }
}

// Run installer if called directly
if (basename($_SERVER['SCRIPT_FILENAME']) === 'Installer.php') {
    Installer::postInstall();
}

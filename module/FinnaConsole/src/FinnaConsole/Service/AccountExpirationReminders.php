<?php
/**
 * Console service for reminding users x days before account expiration
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2016.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Service
 * @author   Jyrki Messo <jyrki.messo@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace FinnaConsole\Service;
use Zend\Db\Sql\Select;
use Zend\ServiceManager\ServiceManager;
use Zend\View\Resolver\AggregateResolver;
use Zend\View\Resolver\TemplatePathStack;

/**
 * Console service for reminding users x days before account expiration
 *
 * @category VuFind
 * @package  Service
 * @author   Jyrki Messo <jyrki.messo@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class AccountExpirationReminders extends AbstractService
{


    /**
     * View renderer
     *
     * @var Zend\View\Renderer\PhpRenderer
     */
    protected $renderer = null;

    /**
     * Table for user accounts
     *
     * @var \VuFind\Db\Table\User
     */
    protected $table = null;

    /**
     * ServiceManager
     *
     * ServiceManager is used for creating VuFind\Mailer objects as needed
     * (mailer is not shared as its connection might time out otherwise).
     *
     * @var ServiceManager
     */
    protected $serviceManager = null;

    /**
     * Constructor
     *
     * @param Finna\Db\Table\User            $table                User table.
     * @param Zend\View\Renderer\PhpRenderer $renderer             View renderer.
     * @param ServiceManager                 $serviceManager       Service manager.
     */
    public function __construct (
        $table, $renderer, $serviceManager
    ) {
        $this->table = $table;
        $this->serviceManager = $serviceManager; 
        $this->renderer = $renderer;
    }

    /**
     * Run service.
     *
     * @param array $arguments Command line arguments.
     *
     * @return boolean success
     */
    public function run($arguments)
    {
        if (!isset($arguments[0]) || (int) $arguments[0] < 180 || !isset($arguments[1]) || !isset($arguments[2])) {
            echo "Usage:\n  php index.php util expiration_reminders <expiration_days> <remind_days_before> <frequency>\n\n"
                . "  Sends a reminder for those users whose account will expire in <remind__days_before> days\n"
                . "  Values below 180 are not accepted for <expiration_days> parameter.\n";
            return false;
        }

        $siteConfig = \VuFind\Config\Locator::getLocalConfigPath("config.ini"); 
        /* vai if (!$path = $this->resolveViewPath($institution, $view)) { ScheduledAlerts.php:stä */
        $this->currentSiteConfig = parse_ini_file($siteConfig, true);

        $users = $this->getUsersToRemind($arguments[0],$arguments[1],$arguments[2]);
        $count = 0;

        foreach ($users as $user) {
            $this->msg("Sending expiration reminder for user " . $user->username);
            $this->sendAccountExpirationReminder($user);
            $count++;
        }

        if ($count === 0) {
            $this->msg('No user accounts to remind.'); /*  */
        } else {
            $this->msg("$count expiring user accounts reminded.");
        }

        return true;
    }

    /**
     * Returns all users that have not been active for given amount of days.
     *
     * @param int $days Expiration limit in days for user accounts
     * @param int $remind_days_before How many days before expiration reminding starts
     * @param int $frequency What is the freqency in days for reminding the user
     *
     * @return User[] users to remind on expiration
     */
    protected function getUsersToRemind($days,$remindDaysBefore,$frequency)
    {

        $remindingDaysBegin = array();
        $remindingDaysEnd = array();

        for ($x = $remindDaysBefore; $x > 0; $x -= $frequency) {
            $remindingDaysBegin[] = date('Y-m-d 00:00:00', strtotime(sprintf('-%d days', (int) $days + $x)));
            $remindingDaysEnd[] = date('Y-m-d 23:59:59', strtotime(sprintf('-%d days', (int) $days + $x)));
        }

        /* For testing: 
          $remindingDaysBegin[] = date('2015-09-01 00:00:00');
          $remindingDaysEnd[] = date('2015-09-31 23:59:59');
        */
      
        $timePeriods = "";

        for ($i = 0; $i < count($remindingDaysBegin); $i++) {
            if (strlen($timePeriods) > 0) {
                $timePeriods .= " OR ";
            }
            $timePeriods .= "(finna_last_login >= '" . $remindingDaysBegin[$i] . "' AND ";
            $timePeriods .= "finna_last_login <= '" . $remindingDaysBegin[$i] . "')"; 
        }

        return $this->table->select(
            function (Select $select) use ($timePeriods) {
                $select->where->notLike('username', 'deleted:%'); 
                $select->where($timePeriods);
                $select->where->notEqualTo(
                    'finna_last_login',
                    '0000-00-00 00:00:00'
                );
            }
        );
    }

    /**
     * Send account expiration reminders for a user.
     *
     * @param \Finna\Db\Table\Row\User $user        User.
     *
     * @return boolean success.
     */
    protected function sendAccountExpirationReminder($user)
    {
        if (!$user->email || trim($user->email) == '') {
            $this->msg(
                "User {$user->username} (id {$user->id})"
                . ' does not have an email address, bypassing due date reminders'
            );
            return false;
        }

        $params = [
            'library' => 'Palvelun_nimi',
            'username' => $user->username,
            'name' => $user->username,
            'email' => $user->email
        ];

        /* TODO: Millä selvitetään asennuksen juuri fiksummin */
        $templateDirs = [
            getenv('VUFIND_LOCAL_DIR') . "/../themes/finna/templates",
        ];

        $resolver = new AggregateResolver();
        $this->renderer->setResolver($resolver);
        $stack = new TemplatePathStack(['script_paths' => $templateDirs]);
        $resolver->attach($stack);

        /* TODO: Kieliversiot */
        $subject = "Käyttäjätunnuksesi vanhenee"; 
        $message = $this->renderer->render('Email/account-expiration-reminder.phtml', $params);

        try {
            $to = $user->email;
            $from = $this->currentSiteConfig['Site']['email'];

            /*
            $this->msg("SUBJ: $subject");
            $this->msg("MSG: $message");
            */

            $this->serviceManager->get('VuFind\Mailer')->send(
                $to, $from, $subject, $message
            );
        } catch (\Exception $e) {
            $this->err(
                "Failed to send expiration reminder to user {$user->username} "
                . " (id {$user->id})"
            );
            $this->err('   ' . $e->getMessage());
            return false;
        }

        /* Tänne ehkä myös lokitiedon kirjoitus kantaan lähetetyistä viesteistä */

        return true;
    }
}

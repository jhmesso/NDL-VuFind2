<?php
/**
 * MyResearch Controller
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2017.
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
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Session\SessionManager;

/**
 * Controller for the user account area.
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class MyResearchController extends \VuFind\Controller\MyResearchController
{
    use OnlinePaymentControllerTrait;
    use CatalogLoginTrait;

    /**
     * Session manager
     *
     * @var SessionManager
     */
    protected $sessionManager;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm             Service manager
     * @param SessionManager          $sessionManager Session manager
     */
    public function __construct(ServiceLocatorInterface $sm,
        SessionManager $sessionManager
    ) {
        parent::__construct($sm);
        $this->sessionManager = $sessionManager;
    }

    /**
     * Catalog Login Action
     *
     * @return mixed
     */
    public function catalogloginAction()
    {
        // Connect to the ILS and check if multiple target support is available
        // Add default driver to result so we can use it on cataloglogin.phtml
        $targets = null;
        $defaultTarget = null;
        $catalog = $this->getILS();
        if ($catalog->checkCapability('getLoginDrivers')) {
            $targets = $catalog->getLoginDrivers();
            $defaultTarget = $catalog->getDefaultLoginDriver();
        }
        $result = $this->createViewModel(
            [
                'targets' => $targets,
                'defaultdriver' => $defaultTarget
            ]
        );

        // Try to find the original action and map it to the corresponding menu item
        // since we were probably forwarded here.
        $requestedAction = '';
        $router = $this->getEvent()->getRouter();
        if ($router) {
            $route = $router->match($this->getRequest());
            if ($route) {
                $requestedAction = $route->getParam('action');
                switch ($requestedAction) {
                case 'ILLRequests':
                    break;
                case 'CheckedOut':
                    $requestedAction = 'checkedout';
                    break;
                default:
                    $requestedAction = lcfirst($requestedAction);
                    break;
                }
            }
        }
        $result->requestedAction = $requestedAction;

        return $result;
    }

    /**
     * Send list of checked out books to view.
     * Added profile to view, so borrow blocks can be shown.
     *
     * @return mixed
     */
    public function checkedoutAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        if ($view = $this->createViewIfUnsupported('getMyTransactions')) {
            return $view;
        }

        $view = parent::checkedoutAction();
        $transactions = count($view->transactions);
        $renewResult = $view->renewResult;
        if (isset($renewResult) && is_array($renewResult)) {
            $renewedCount = 0;
            $renewErrorCount = 0;
            foreach ($renewResult as $renew) {
                if ($renew['success']) {
                    $renewedCount++;
                } else {
                    $renewErrorCount++;
                }
            }
            $flashMsg = $this->flashMessenger();
            if ($renewedCount > 0) {
                $msg = $this->translate(
                    'renew_ok', ['%%count%%' => $renewedCount,
                    '%%transactionscount%%' => $transactions]
                );
                $flashMsg->setNamespace('info')->addMessage($msg);
            }
            if ($renewErrorCount > 0) {
                $msg = $this->translate(
                    'renew_failed',
                    ['%%count%%' => $renewErrorCount]
                );
                $flashMsg->setNamespace('error')->addMessage($msg);
            }
        }
        // Handle sorting
        $currentSort = $this->getRequest()->getQuery('sort', 'duedate');
        $view->sortList = [
            'duedate' => [
                'desc' => 'Due Date',
                'url' => '?sort=duedate',
                'selected' => $currentSort == 'duedate'
            ],
            'title' => [
                'desc' => 'Title',
                'url' => '?sort=title',
                'selected' => $currentSort == 'title'
            ]
        ];

        $date = $this->serviceLocator->get('VuFind\DateConverter');
        $sortFunc = function ($a, $b) use ($currentSort, $date) {
            $aDetails = $a->getExtraDetail('ils_details');
            $bDetails = $b->getExtraDetail('ils_details');
            if ($currentSort == 'title') {
                $aTitle = is_a($a, 'VuFind\\RecordDriver\\SolrDefault')
                     && !is_a($a, 'VuFind\\RecordDriver\\Missing')
                     ? $a->getSortTitle() : '';
                if (!$aTitle) {
                    $aTitle = isset($aDetails['title'])
                        ? $aDetails['title'] : '';
                }
                $bTitle = is_a($b, 'VuFind\\RecordDriver\\SolrDefault')
                     && !is_a($b, 'VuFind\\RecordDriver\\Missing')
                     ? $b->getSortTitle() : '';
                if (!$bTitle) {
                    $bTitle = isset($bDetails['title'])
                        ? $bDetails['title'] : '';
                }
                $result = strcmp($aTitle, $bTitle);
                if ($result != 0) {
                    return $result;
                }
            }

            try {
                $aDate = isset($aDetails['duedate'])
                    ? $date->convertFromDisplayDate('U', $aDetails['duedate'])
                    : 0;
                $bDate = isset($bDetails['duedate'])
                    ? $date->convertFromDisplayDate('U', $bDetails['duedate'])
                    : 0;
            } catch (Exception $e) {
                return 0;
            }

            return $aDate - $bDate;
        };

        $transactions = $view->transactions;
        usort($transactions, $sortFunc);
        $view->transactions = $transactions;
        $view->blocks = $this->getILS()->getAccountBlocks($patron);
        return $view;
    }

    /**
     * Send list of checkout history to view.
     *
     * @return mixed
     */
    public function checkoutHistoryAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        if ($view = $this->createViewIfUnsupported('getMyTransactionHistory')) {
            return $view;
        }

        $view = $this->createViewModel();

        // Connect to the ILS:
        $catalog = $this->getILS();

        // Display account blocks, if any:
        $this->addAccountBlocksToFlashMessenger($catalog, $patron);

        // Get page and page size:
        $page = $this->params()->fromQuery('page', 1);
        $config = $this->getConfig();
        $limit = isset($config->Catalog->checkout_history_page_size)
            ? $config->Catalog->checkout_history_page_size : 50;

        // Handle sorting
        $currentSort = $this->getRequest()->getQuery('sort', 'checkout desc');
        $view->sortList = [
            'checkout desc' => [
                'desc' => 'sort_checkout_date_desc',
                'url' => '?sort=checkout%20desc',
                'selected' => $currentSort == 'checkout desc'
            ],
            'checkout asc' => [
                'desc' => 'sort_checkout_date_asc',
                'url' => '?sort=checkout%20asc',
                'selected' => $currentSort == 'checkout asc'
            ],
            'return desc' => [
                'desc' => 'sort_return_date_desc',
                'url' => '?sort=return%20desc',
                'selected' => $currentSort == 'return desc'
            ],
            'return asc' => [
                'desc' => 'sort_return_date_asc',
                'url' => '?sort=return%20asc',
                'selected' => $currentSort == 'return asc'
            ],
            'duedate desc' => [
                'desc' => 'sort_duedate_desc',
                'url' => '?sort=duedate%20desc',
                'selected' => $currentSort == 'duedate desc'
            ],
            'duedate asc' => [
                'desc' => 'sort_duedate_asc',
                'url' => '?sort=duedate%20asc',
                'selected' => $currentSort == 'duedate asc'
            ]
        ];
        // Get checkout history details:
        $params = [
            'start' => ($page - 1) * $limit,
            'limit' => $limit,
            'sort' => $currentSort
        ];
        $result = $catalog->getMyTransactionHistory($patron, $params);

        $adapter = new \Zend\Paginator\Adapter\NullFill($result['count']);
        $paginator = new \Zend\Paginator\Paginator($adapter);
        $paginator->setItemCountPerPage($limit);
        $paginator->setCurrentPageNumber($page);

        $transactions = $hiddenTransactions = [];
        foreach ($result['transactions'] as $current) {
            $transactions[] = $this->getDriverForILSRecord($current);
        }

        $view->transactions = $transactions;
        $view->paginator = $paginator;
        $view->count = $result['count'];
        return $view;
    }

    /**
     * Purge checkout history action.
     *
     * @return mixed
     */
    public function purgeCheckoutHistoryAction()
    {
        if ($this->formWasSubmitted('cancel', false)) {
            return $this->redirect()->toRoute('myresearch-checkouthistory');
        }

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        if ($view = $this->createViewIfUnsupported('purgeTransactionHistory')) {
            return $view;
        }

        // Set up CSRF:
        $this->csrf = new \Zend\Validator\Csrf(
            [
                'session' => new \Zend\Session\Container(
                    'csrf', $this->sessionManager
                ),
                'salt' => isset($this->config->Security->HMACkey)
                    ? $this->config->Security->HMACkey : 'VuFindCsrfSalt',
            ]
        );

        if ($this->formWasSubmitted('submit', false)) {
            $csrf = $this->getRequest()->getPost()->get('csrf');
            if (!$this->csrf->isValid($csrf)) {
                throw new \Exception('An error has occurred');
            }
            $catalog = $this->getILS();
            $result = $catalog->purgeTransactionHistory($patron);
            $this->flashMessenger()->addMessage(
                $result['status'], $result['success'] ? 'error' : 'info'
            );
            return $this->redirect()->toRoute('myresearch-checkouthistory');
        }

        $view = $this->createViewModel();
        $view->csrf = $this->csrf->getHash(true);

        return $view;
    }

    /**
     * Login Action
     *
     * @return mixed
     */
    public function loginAction()
    {
        $config = $this->getConfig();

        if (empty($config->TermsOfService->enabled)
            || !isset($config->TermsOfService->version)
        ) {
            return parent::loginAction();
        }

        $cookieName = 'finnaTermsOfService';

        $cookieManager = $this->serviceLocator->get('VuFind\CookieManager');
        $cookie = $cookieManager->get($cookieName);
        if ($cookie && $cookie === $config->TermsOfService->version) {
            return parent::loginAction();
        }

        $fromTermsPage = false;
        if ($this->formWasSubmitted('submit', false)
            && $this->params()->fromPost('acceptTerms', false) === '1'
        ) {
            $expire = time() + 5 * 365 * 60 * 60 * 24; // 5 years
            $cookieManager->set(
                $cookieName, $config->TermsOfService->version, $expire
            );
            $this->getRequest()->getPost()->offsetUnset('submit');
            $fromTermsPage = true;
            $view = parent::loginAction();
            $view->fromTermsPage = $fromTermsPage;
            return $view;
        }
        $view = $this->createViewModel();
        $view->setTemplate('myresearch/terms.phtml');

        return $view;
    }

    /**
     * Send user's saved favorites from a particular list to the view
     *
     * @return mixed
     */
    public function mylistAction()
    {
        $view = parent::mylistAction();
        $user = $this->getUser();

        if ($results = $view->results) {
            $list = $results->getListObject();

            // Redirect anonymous users and list visitors to public list URL
            if ($list && $list->isPublic()
                && (!$user || $user->id != $list->user_id)
            ) {
                return $this->redirect()->toRoute('list-page', ['lid' => $list->id]);
            }
            if ($list) {
                $this->rememberCurrentSearchUrl();
            } else {
                $memory  = $this->serviceLocator->get('VuFind\Search\Memory');
                $memory->rememberSearch(
                    $this->url()->fromRoute('myresearch-favorites')
                );
            }
        }

        if (!$user) {
            return $view;
        }

        $view->sortList = $this->createSortList($results->getListObject());

        return $view;
    }

    /**
     * Show user's own favorite list (max. 1000) to the view
     *
     * @return mixed
     */
    public function sortListAction()
    {
        // Fail if lists are disabled:
        if (!$this->listsEnabled()) {
            throw new ForbiddenException('Lists disabled');
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }

        $listId = $this->params()->fromRoute('id');
        if (null === $listId) {
            throw new ListPermissionException('Cannot sort all favorites list');
        }

        if ($this->formWasSubmitted('cancelOrdering')) {
            return $this->redirect()->toRoute('userList', ['id' => $listID]);
        }
        if ($this->formWasSubmitted('saveOrdering')) {
            $orderedList = json_decode(
                $this->params()->fromPost('orderedList'), true
            );
            $table = $this->getTable('UserResource');
            $listID = $this->params()->fromPost('list_id');
            if (empty($listID) || empty($orderedList)
                || !$table->saveCustomFavoriteOrder($user->id, $listID, $orderedList)
            ) {
                $this->flashMessenger()->addErrorMessage('An error has occurred');
            } else {
                // inLightbox (only instance)
                if ($this->getRequest()->getQuery('layout', 'no') === 'lightbox'
                    || 'layout/lightbox' == $this->layout()->getTemplate()
                ) {
                    $response = $this->getResponse();
                    $response->setStatusCode(205);
                    return $response;
                }
                return $this->redirect()->toRoute('userList', ['id' => $listID]);
            }
        }

        // If we got this far, we just need to display the favorites:
        try {
            $runner = $this->serviceLocator->get('VuFind\SearchRunner');

            // We want to merge together GET, POST and route parameters to
            // initialize our search object:
            $request = $this->getRequest()->getQuery()->toArray()
                + $this->getRequest()->getPost()->toArray()
                + ['id' => $listId];

            $setupCallback = function ($runner, $params, $searchId) {
                $params->setLimit(1000);
            };
            $results = $runner->run($request, 'Favorites', $setupCallback);

            return $this->createViewModel(
                ['params' => $results->getParams(), 'results' => $results]
            );
        } catch (ListPermissionException $e) {
            if (!$this->getUser()) {
                return $this->forceLogin();
            }
            throw $e;
        }

        return $view;
    }

    /**
     * Gather user profile data
     *
     * @return mixed
     */
    public function profileAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        $values = $this->getRequest()->getPost();
        if (isset($values->due_date_reminder)) {
            $user->setFinnaDueDateReminder($values->due_date_reminder);
            $this->flashMessenger()->setNamespace('info')
                ->addMessage('profile_update');
        }
        if ($this->formWasSubmitted('saveUserProfile')) {
            $validator = new \Zend\Validator\EmailAddress();
            if ('' === $values->email || $validator->isValid($values->email)) {
                $user->email = $values->email;
                $user->save();
                $this->flashMessenger()->setNamespace('info')
                    ->addMessage('profile_update');
            } else {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage('profile_update_failed');
            }
        }

        $view = parent::profileAction();
        $profile = $view->profile;
        $patron = $this->catalogLogin();

        if (is_array($patron) && $this->formWasSubmitted('saveLibraryProfile')) {
            if ($this->processLibraryDataUpdate($patron, $values, $user)) {
                $this->flashMessenger()->setNamespace('info')
                    ->addMessage('profile_update');
            }
            $view = parent::profileAction();
            $profile = $view->profile;
        }

        $parentTemplate = $view->getTemplate();
        // If returned view is not profile view, show it below our profile part.
        if ($parentTemplate != '' && $parentTemplate != 'myresearch/profile') {
            $childView = $this->createViewModel();
            $childView->setTemplate('myresearch/profile');

            $compoundView = $this->createViewModel();
            $compoundView->addChild($childView, 'child');
            $compoundView->addChild($view, 'parent');

            return $compoundView;
        }

        // Check if due date reminder settings should be displayed
        $config = $this->getConfig();
        $view->hideDueDateReminder = $user->finna_due_date_reminder == 0
            && isset($config->Site->hideDueDateReminder)
            && $config->Site->hideDueDateReminder;
        if (!$view->hideDueDateReminder && is_array($patron)) {
            $catalog = $this->getILS();
            $ddrConfig = $catalog->getConfig('dueDateReminder', $patron);
            if (isset($ddrConfig['enabled']) && !$ddrConfig['enabled']) {
                $view->hideDueDateReminder = true;
            }
        }

        // Check whether to hide email address in profile
        $view->hideProfileEmailAddress
            = isset($config->Site->hideProfileEmailAddress)
            && $config->Site->hideProfileEmailAddress;

        if (is_array($patron)) {
            $view->blocks = $this->getILS()->getAccountBlocks($patron);
        }

        return $view;
    }

    /**
     * Library information address change form
     *
     * @return mixed
     */
    public function changeProfileAddressAction()
    {
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }
        if ($view = $this->createViewIfUnsupported('updateAddress', true)) {
            return $view;
        }

        $catalog = $this->getILS();
        $updateConfig = $catalog->checkFunction('updateAddress', $patron);
        $profile = $catalog->getMyProfile($patron);
        $fields = [];
        if (!empty($updateConfig['fields'])) {
            foreach ($updateConfig['fields'] as $fieldConfig) {
                list($label, $field) = explode(':', $fieldConfig);
                $fields[$field] = ['label' => $label];
            }
        }
        if (empty($fields)) {
            $fields = [
                'address1' => ['label' => 'Address'],
                'zip' => ['label' => 'Zip'],
                'city' => ['label' => 'City'],
                'country' => ['label' => 'Country']
            ];

            if (false === $catalog->checkFunction('updateEmail', $patron)) {
                $fields['email'] = ['label' => 'Email'];
            }
            if (false === $catalog->checkFunction('updatePhone', $patron)) {
                $fields['phone'] = ['label' => 'Phone'];
            }
            if (false === $catalog->checkFunction('updateSmsNumber', $patron)) {
                $fields['sms_number'] = ['label' => 'SMS Number'];
            }
        }

        $view = $this->createViewModel();
        $view->fields = $fields;

        if ($this->formWasSubmitted('address_change_request')) {
            $data = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);

            $config = $this->getILS()->getConfig('updateAddress', $patron);

            if (isset($config['method']) && 'driver' === $config['method']) {
                if (false === $catalog->checkFunction('updateAddress', $patron)) {
                    throw new \Exception(
                        'ILS driver does not support updating contact information'
                    );
                }
                $result = $catalog->updateAddress($patron, $data);
                if ($result['success']) {
                    $view->requestCompleted = true;
                    $this->flashMessenger()->addSuccessMessage($result['status']);
                } else {
                    $this->flashMessenger()->addErrorMessage($result['status']);
                }
            } else {
                if (!isset($config['emailAddress'])) {
                    throw new \Exception(
                        'Missing emailAddress in ILS updateAddress settings'
                    );
                }
                $recipient = $config['emailAddress'];

                $this->sendChangeRequestEmail(
                    $patron, $profile, $data, $fields, $recipient,
                    'Yhteystietojen muutospyyntö', 'change-address'
                );
                $this->flashMessenger()
                    ->addSuccessMessage('request_change_done');
                $view->requestCompleted = true;
            }
        }

        $view->profile = $profile;
        $view->config = $updateConfig;
        $view->setTemplate('myresearch/change-address-settings');
        return $view;
    }

    /**
     * Messaging settings change form
     *
     * @return mixed
     */
    public function changeMessagingSettingsAction()
    {
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }
        $catalog = $this->getILS();
        $profile = $catalog->getMyProfile($patron);
        $view = $this->createViewModel();
        $config = $catalog->getConfig('updateMessagingSettings', $patron);

        if ($this->formWasSubmitted('messaging_update_request')) {
            if (isset($config['method']) && 'driver' === $config['method']) {
                $data = $profile['messagingServices'];
                $request = $this->getRequest();
                // Collect results from the POST request and update settings
                foreach ($data as $serviceId => &$service) {
                    foreach ($service['settings'] as $settingId => &$setting) {
                        if (!empty($setting['readonly'])) {
                            continue;
                        }
                        if ('boolean' == $setting['type']) {
                            $setting['active'] = (bool)$request->getPost(
                                $serviceId . '_' . $settingId, false
                            );
                        } elseif ('select' == $setting['type']) {
                            $setting['value'] = $request->getPost(
                                $serviceId . '_' . $settingId, ''
                            );
                        } elseif ('multiselect' == $setting['type']) {
                            foreach ($setting['options'] as $optionId
                                => &$option
                            ) {
                                $option['active'] = (bool)$request->getPost(
                                    $serviceId . '_' . $settingId . '_' . $optionId,
                                    false
                                );
                            }
                        }
                    }
                }
                $result = $catalog->updateMessagingSettings($patron, $data);
                if ($result['success']) {
                    $this->flashMessenger()->addSuccessMessage($result['status']);
                    $view->requestCompleted = true;
                } else {
                    $this->flashMessenger()->addErrorMessage($result['status']);
                }
            } else {
                if (!isset($config['emailAddress'])) {
                    throw new \Exception(
                        'Missing emailAddress in ILS updateMessagingSettings'
                    );
                }
                $data = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
                $data['pickUpNotice'] = $this->translate(
                    'messaging_settings_method_' . $data['pickUpNotice'],
                    null,
                    $data['pickUpNotice']
                );
                $data['overdueNotice'] = $this->translate(
                    'messaging_settings_method_' . $data['overdueNotice'],
                    null,
                    $data['overdueNotice']
                );
                if ($data['dueDateAlert'] == 0) {
                    $data['dueDateAlert']
                        = $this->translate('messaging_settings_method_none');
                } elseif ($data['dueDateAlert'] == 1) {
                    $data['dueDateAlert']
                        = $this->translate('messaging_settings_num_of_days');
                } else {
                    $data['dueDateAlert'] = $this->translate(
                        'messaging_settings_num_of_days_plural',
                        ['%%days%%' => $data['dueDateAlert']]
                    );
                }

                $recipient = $config['emailAddress'];

                $this->sendChangeRequestEmail(
                    $patron, $profile, $data, [], $recipient,
                    'Viestiasetusten muutospyyntö', 'change-messaging-settings'
                );
                $this->flashMessenger()
                    ->addSuccessMessage('request_change_done');
                $view->requestCompleted = true;
            }
        }

        if (isset($profile['messagingServices'])) {
            $view->services = $profile['messagingServices'];
            $emailDays = [];
            foreach ([1, 2, 3, 4, 5] as $day) {
                if ($day == 1) {
                    $label = $this->translate('messaging_settings_num_of_days');
                } else {
                    $label = $this->translate(
                        'messaging_settings_num_of_days_plural',
                        ['%%days%%' => $day]
                    );
                }
                $emailDays[] = $label;
            }

            $view->emailDays = $emailDays;
            $view->days = [1, 2, 3, 4, 5];
            $view->profile = $profile;
        }
        if (isset($config['method']) && 'driver' === $config['method']) {
            $view->setTemplate('myresearch/change-messaging-settings-driver');
            $view->approvalRequired = !empty($config['approvalRequired']);
        } else {
            $view->setTemplate('myresearch/change-messaging-settings');
        }
        return $view;
    }

    /**
     * Delete account form
     *
     * @return mixed
     */
    public function deleteAccountAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        $view = $this->createViewModel();
        $view->accountDeleted = false;
        $view->token = $this->getSecret($this->getUser());
        if ($this->formWasSubmitted('submit')) {
            $success = $this->processDeleteAccount();
            if ($success) {
                $view->accountDeleted = true;
                $view->redirectUrl = $this->getAuthManager()->logout(
                    $this->getServerUrl('home')
                );
            }
        } elseif ($this->formWasSubmitted('reset')) {
            return $this->redirect()->toRoute(
                'default', ['controller' => 'MyResearch', 'action' => 'Profile']
            );
        }
        $view->setTemplate('myresearch/delete-account');
        return $view;
    }

    /**
     * Return the Favorites sort list options.
     *
     * @return array
     */
    public static function getFavoritesSortList()
    {
        return [
            'custom_order' => 'sort_custom_order',
            'id desc' => 'sort_saved',
            'id' => 'sort_saved asc',
            'title' => 'sort_title',
            'author' => 'sort_author',
            'year desc' => 'sort_year',
            'year' => 'sort_year asc',
            'format' => 'sort_format'
        ];
    }

    /**
     * Send list of holds to view
     *
     * @return mixed
     */
    public function holdsAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        if ($view = $this->createViewIfUnsupported('getMyHolds')) {
            return $view;
        }

        $view = parent::holdsAction();
        $view->recordList = $this->orderAvailability($view->recordList);
        $view->blocks = $this->getILS()->getAccountBlocks($patron);
        return $view;
    }

    /**
     * Save favorite custom order into DB
     *
     * @return mixed
     */
    public function saveCustomOrderAction()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }

        if ($this->formWasSubmitted('opcode')
            && $this->params()->fromPost('opcode') == 'save_order'
        ) {
            $this->session->url = empty($listID)
                ? $this->url()->fromRoute('myresearch-favorites')
                : $this->url()->fromRoute('userList', ['id' => $listID]);

            $orderedList = $this->params()->fromPost('orderedList');
            $table = $this->getTable('UserResource');
            $listID = $this->params()->fromPost('list_id');
            if (empty($listID) || empty($orderedList)
                || !$table->saveCustomFavoriteOrder($user->id, $listID, $orderedList)
            ) {
                $this->flashMessenger()->addErrorMessage('An error has occurred');
            }
            return $this->redirect()->toRoute('userList', ['id' => $listID]);
        } else {
            return $this->redirect()->toRoute('userList', ['id' => $listID]);
        }
    }

    /**
     * Save alert schedule for a saved search into DB
     *
     * @return mixed
     */
    public function savesearchAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }
        $schedule = $this->params()->fromQuery('schedule', false);
        $sid = $this->params()->fromQuery('searchid', false);

        if ($schedule !== false && $sid !== false) {
            $search = $this->getTable('Search');
            $baseurl = rtrim($this->getServerUrl('home'), '/');
            $row = $search->select(
                ['id' => $sid, 'user_id' => $user->id]
            )->current();
            if ($row) {
                $row->setSchedule($schedule, $baseurl);
            }
            return $this->redirect()->toRoute('search-history');
        } else {
            return parent::savesearchAction();
        }
    }

    /**
     * Send list of storage retrieval requests to view
     *
     * @return mixed
     */
    public function storageRetrievalRequestsAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        if ($view = $this->createViewIfUnsupported('StorageRetrievalRequests', true)
        ) {
            return $view;
        }

        $view = parent::storageRetrievalRequestsAction();
        $view->recordList = $this->orderAvailability($view->recordList);
        $view->blocks = $this->getILS()->getAccountBlocks($patron);
        return $view;
    }

    /**
     * Send list of ill requests to view
     *
     * @return mixed
     */
    public function illRequestsAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        if ($view = $this->createViewIfUnsupported('ILLRequests', true)) {
            return $view;
        }

        $view = parent::illRequestsAction();
        $view->recordList = $this->orderAvailability($view->recordList);
        $view->blocks = $this->getILS()->getAccountBlocks($patron);
        return $view;
    }

    /**
     * Send list of fines to view
     *
     * @return mixed
     */
    public function finesAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        if ($view = $this->createViewIfUnsupported('getMyFines')) {
            return $view;
        }

        $view = parent::finesAction();
        $view->blocks = $this->getILS()->getAccountBlocks($patron);
        if (isset($patron['source'])) {
            $this->handleOnlinePayment($patron, $view->fines, $view);
        }
        return $view;
    }

    /**
     * Unsubscribe a scheduled alert for a saved search.
     *
     * @return mixed
     */
    public function unsubscribeAction()
    {
        $id = $this->params()->fromQuery('id', false);
        $key = $this->params()->fromQuery('key', false);
        $type = $this->params()->fromQuery('type', 'alert');

        if ($id === false || $key === false) {
            throw new \Exception('Missing parameters.');
        }

        $view = $this->createViewModel();

        if ($this->params()->fromQuery('confirm', false) == 1) {
            if ($type == 'alert') {
                $search
                    = $this->getTable('Search')->select(['id' => $id])->current();
                if (!$search) {
                    throw new \Exception('Invalid parameters.');
                }
                $user = $this->getTable('User')->getById($search->user_id);

                $secret = $search->getUnsubscribeSecret(
                    $this->serviceLocator->get('VuFind\HMAC'), $user
                );
                if ($key !== $secret) {
                    throw new \Exception('Invalid parameters.');
                }
                $search->setSchedule(0);
            } else if ($type == 'reminder') {
                $user = $this->getTable('User')->select(['id' => $id])->current();
                if (!$user) {
                    throw new \Exception('Invalid parameters.');
                }
                $dueDateTable = $this->getTable('due-date-reminder');
                $secret = $dueDateTable->getUnsubscribeSecret(
                    $this->serviceLocator->get('VuFind\HMAC'), $user, $user->id
                );
                if ($key !== $secret) {
                    throw new \Exception('Invalid parameters.');
                }
                $user->setFinnaDueDateReminder(0);
                // Remove due date reminder from all cards too
                foreach ($user->getLibraryCards() as $card) {
                    if ($card->finna_due_date_reminder != 0) {
                        $card = $user->getLibraryCard($card->id);
                        $card->finna_due_date_reminder = 0;
                        $card->save();
                    }
                }
            }
            $view->success = true;
        } else {
            $view->unsubscribeUrl
                = $this->getRequest()->getRequestUri() . '&confirm=1';
        }
        return $view;
    }

    /**
     * Creates a JSON file of logged in user's saved searches and lists and sends
     * the file to the browser.
     *
     * @return mixed
     */
    public function exportAction()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirect()->toRoute(
                'default', ['controller' => 'MyResearch', 'action' => 'Login']
            );
        }

        $exportData = [
            'searches' => $this->exportSavedSearches($user->id),
            'lists' => $this->exportUserLists($user->id)
        ];
        $json = json_encode($exportData);
        $timestamp = strftime('%Y-%m-%d-%H%M');
        $filename = "finna-export-$timestamp.json";
        $response = $this->getResponse();
        $response->setContent($json);
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-Type', 'application/json')
            ->addHeaderLine(
                'Content-Disposition',
                'attachment; filename="' . $filename . '"'
            )
            ->addHeaderLine('Content-Length', strlen($json));

        return $this->response;
    }

    /**
     * Display dialog for importing favorites.
     *
     * @return mixed
     */
    public function importAction()
    {
    }

    /**
     * Add account blocks to the flash messenger as errors.
     *
     * @param \VuFind\ILS\Connection $catalog Catalog connection
     * @param array                  $patron  Patron details
     *
     * @return void
     */
    public function addAccountBlocksToFlashMessenger($catalog, $patron)
    {
        // We don't use the flash messenger for blocks.
    }

    /**
     * Create sort list.
     * If no sort option selected, set first one from the list to default.
     *
     * @param list $list List object
     *
     * @return array
     */
    protected function createSortList($list)
    {
        $table = $this->getTable('UserResource');

        $sortOptions = self::getFavoritesSortList();
        $sort = isset($_GET['sort']) ? $_GET['sort'] : false;
        if (!$sort) {
            reset($sortOptions);
            $sort = key($sortOptions);
        }
        $sortList = [];

        if (empty($list) || !$table->isCustomOrderAvailable($list->id)) {
            array_shift($sortOptions);
            if ($sort == 'custom_order') {
                $sort = 'id desc';
            }
        }

        foreach ($sortOptions as $key => $value) {
            $sortList[$key] = [
                'desc' => $value,
                'selected' => $key === $sort
            ];
        }
        return $sortList;
    }

    /**
     * Check if current library card supports a function. If not supported, show
     * a message and a notice about the possibility to change library card.
     *
     * @param string  $function      Function to check
     * @param boolean $checkFunction Use checkFunction() if true,
     * checkCapability() otherwise
     *
     * @return mixed \Zend\View if the function is not supported, false otherwise
     */
    protected function createViewIfUnsupported($function, $checkFunction = false)
    {
        $params = ['patron' => $this->catalogLogin()];
        if ($checkFunction) {
            $supported = $this->getILS()->checkFunction($function, $params);
        } else {
            $supported = $this->getILS()->checkCapability($function, $params);
        }

        if (!$supported) {
            $view = $this->createViewModel();
            $view->noSupport = true;
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('no_ils_support_for_' . strtolower($function));
            return $view;
        }
        return false;
    }

    /**
     * Order available records to beginning of the record list
     *
     * @param type $recordList list to order
     *
     * @return type
     */
    protected function orderAvailability($recordList)
    {
        if ($recordList === null) {
            return [];
        }

        $availableRecordList = [];
        $recordListBasic = [];
        foreach ($recordList as $item) {
            if (isset($item->getExtraDetail('ils_details')['available'])
                && $item->getExtraDetail('ils_details')['available']
            ) {
                $availableRecordList[] = $item;
            } else {
                $recordListBasic[] = $item;
            }
        }
        return array_merge($availableRecordList, $recordListBasic);
    }

    /**
     * Utility function for generating a token.
     *
     * @param object $user current user
     *
     * @return string token
     */
    protected function getSecret($user)
    {
        $data = [
            'id' => $user->id,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'created' => $user->created,
        ];
        $token = new \VuFind\Crypt\HMAC('usersecret');
        return $token->generate(array_keys($data), $data);
    }

    /**
     * Append current URL to search memory so that return links on
     * record pages opened from a list point back to the list page.
     *
     * @return void
     */
    protected function rememberCurrentSearchUrl()
    {
        $memory  = $this->serviceLocator->get('VuFind\Search\Memory');
        $listUrl = $this->getRequest()->getRequestUri();
        /*$routeName = $publicView ? 'list-page' : 'userList';
        $idParamName = $publicView ? 'lid' : 'id';
        $request = $this->getRequest();
        $queryParams = [];
        if ($view = $request->getQuery('view')) {
            $queryParams['view'] = $view;
        }
        if ($page = $request->getQuery('page')) {
            $queryParams['page'] = $page;
        }
        if ($filter = $request->getQuery('filter')) {
            $queryParams['filter'] = $filter;
        }
        $listUrl = $this->url()->fromRoute(
            $routeName, [$idParamName => $id], ['query' => $queryParams]
        );*/
        $memory->rememberSearch($listUrl);
    }

    /**
     * Change phone number, email and checkout history state from library info.
     *
     * @param array  $patron patron data
     * @param object $values form values
     *
     * @return bool
     */
    protected function processLibraryDataUpdate($patron, $values)
    {
        // Connect to the ILS:
        $catalog = $this->getILS();

        $success = true;
        if (isset($values->profile_email)) {
            $validator = new \Zend\Validator\EmailAddress();
            if ($validator->isValid($values->profile_email)
                && $catalog->checkFunction('updateEmail', $patron)
            ) {
                // Update email
                $result = $catalog->updateEmail($patron, $values->profile_email);
                if (!$result['success']) {
                    $this->flashMessenger()->addErrorMessage($result['status']);
                    $success = false;
                }
            }
        }
        // Update phone
        if (isset($values->profile_tel)
            && $catalog->checkFunction('updatePhone', $patron)
        ) {
            $result = $catalog->updatePhone($patron, $values->profile_tel);
            if (!$result['success']) {
                $this->flashMessenger()->addErrorMessage($result['status']);
                $success = false;
            }
        }
        // Update SMS Number
        if (isset($values->profile_sms_number)
            && $catalog->checkFunction('updateSmsNumber', $patron)
        ) {
            $result = $catalog->updateSmsNumber(
                $patron, $values->profile_sms_number
            );
            if (!$result['success']) {
                $this->flashMessenger()->addErrorMessage($result['status']);
                $success = false;
            }
        }
        // Update checkout history state
        if (isset($values->checkout_history)
            && $catalog->checkFunction('updateTransactionHistoryState', $patron)
        ) {
            $result = $catalog->updateTransactionHistoryState(
                $patron, $values->checkout_history
            );
            if (!$result['success']) {
                $this->flashMessenger()->addErrorMessage($result['status']);
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Delete user account for MyResearch module
     *
     * @return boolean
     */
    protected function processDeleteAccount()
    {
        $user = $this->getUser();

        if (!$user) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('You must be logged in first');
            return false;
        }

        $token = $this->getRequest()->getPost('token', null);
        if (empty($token)) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('Missing token');
            return false;
        }
        if ($token !== $this->getSecret($user)) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('Invalid token');
            return false;
        }

        $success = $user->anonymizeAccount();

        if (!$success) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('delete_account_failure');
        }
        return $success;
    }

    /**
     * Send a change request message (e.g. address change) to the library
     *
     * @param array  $patron    Patron
     * @param array  $profile   Patron profile
     * @param array  $data      Change data
     * @param array  $fields    Form fields for address change request
     * @param string $recipient Email recipient
     * @param string $subject   Email subject
     * @param string $template  Email template
     *
     * @return void
     */
    protected function sendChangeRequestEmail($patron, $profile, $data, $fields,
        $recipient, $subject, $template
    ) {
        list($library, $username) = explode('.', $patron['cat_username']);
        $library = $this->translate("source_$library", null, $library);
        $name = trim(
            (isset($patron['firstname']) ? $patron['firstname'] : '')
            . ' '
            . (isset($patron['lastname']) ? $patron['lastname'] : '')
        );
        $email = isset($patron['email']) ? $patron['email'] : '';
        if (!$email) {
            $user = $this->getUser();
            if (!empty($user['email'])) {
                $email = $user['email'];
            }
        }

        $params = [
            'library' => $library,
            'username' => $patron['cat_username'],
            'name' => $name,
            'email' => $email,
            'patron' => $patron,
            'profile' => $profile,
            'data' => $data,
            'fields' => $fields
        ];
        $renderer = $this->getViewRenderer();
        $message = $renderer->render("Email/$template.phtml", $params);
        $subject = $this->getConfig()->Site->title . ": $subject";
        $from = $this->getConfig()->Site->email;

        $this->serviceLocator->get('VuFind\Mailer')->send(
            $recipient, $from, $subject, $message
        );
    }

    /**
     * Exports user's saved searches into an array.
     *
     * @param int $userId User id
     *
     * @return array Saved searches
     */
    protected function exportSavedSearches($userId)
    {
        $savedSearches = $this->getTable('Search')->getSavedSearches($userId);
        $getSearchObject = function ($search) {
            return $search['search_object'];
        };
        return array_map($getSearchObject, $savedSearches->toArray());
    }

    /**
     * Exports user's saved lists into an array.
     *
     * @param int $userId User id
     *
     * @return array Saved user lists
     */
    protected function exportUserLists($userId)
    {
        $user = $this->getTable('User')->getById($userId);
        $runner = $this->serviceLocator->get('VuFind\SearchRunner');

        $getTag = function ($tag) {
            return $tag['tag'];
        };

        $setupCallback = function ($searchRunner, $params, $runningSearchId) {
            $params->setLimit(1000);
        };

        $userLists = [];
        foreach ($user->getLists() as $list) {
            $listRecords = $runner->run(
                ['id' => $list->id], 'Favorites', $setupCallback
            );
            $outputList = [
                'title' => $list->title,
                'description' => $list->description,
                'public' => $list->public,
                'records' => []
            ];

            foreach ($listRecords->getResults() as $record) {
                $userResource = $user->getSavedData(
                    $record->getUniqueID(),
                    $list->id,
                    $record->getSourceIdentifier()
                )->current();

                $notes = $record->getListNotes($list->id, $user->id);
                $tags = $record->getTags($list->id, $user->id);
                $outputList['records'][] = [
                    'id' => $record->getUniqueID(),
                    'source' => $record->getSourceIdentifier(),
                    'notes' => !empty($notes) ? $notes[0] : null,
                    'tags' => array_map($getTag, $tags->toArray()),
                    'order' => $userResource
                        ? $userResource->finna_custom_order_index
                        : null
                ];
            }

            $userLists[] = $outputList;
        }

        return $userLists;
    }
}

<?php
/**
 * Book Bag / Bulk Action Controller
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Jyrki Messo <jyrki.messo@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace Finna\Controller;

/**
 * Book Bag / Bulk Action Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Jyrki Messo <jyrki.messo@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class CartController extends \VuFind\Controller\CartController
{
    /**
     * Process bulk actions from the MyResearch area; most of this is only necessary
     * when Javascript is disabled.
     *
     * @return mixed
     */
    public function myresearchbulkAction()
    {
        if (! empty($this->params()->fromPost('saveCustomFavoritesOrder', ''))) {
            $listID = $this->params()->fromPost('listID');
            $this->session->url = empty($listID)
                  ? $this->url()->fromRoute('myresearch-favorites')
                  : $this->url()->fromRoute('userList', ['id' => $listID]);
            $controller = 'MyResearch';
            $action = 'SaveCustomFavoritesOrder';
            return $this->forwardTo($controller, $action);
        } else {
            return parent::myresearchbulkAction();
        }
    }
}

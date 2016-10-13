<?php
/**
 * Table Definition for due date reminders.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Jyrki Messo <jyrki.messo@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Db\Table;

/**
 * Table Definition for due date reminders.
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Jyrki Messo <jyrki.messo@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class FavoriteOrder extends \VuFind\Db\Table\Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct(
            'finna_favorite_order', 'Finna\Db\Row\FavoriteOrder'
        );
    }

    /**
     * Add favorite list order
     *
     * @param int    $user_id       User_id
     * @param int    $list_id       List_id
     * @param string $resource_list Ordered List of Resources
     *
     * @return boolean
     */
    public function saveFavoriteOrder($user_id,$list_id,$resource_list)
    {
        try {
            if ($row = $this->select(
                ['user_id' => $user_id,'list_id' => $list_id]
            )->current()) {
                $row->resource_list = $resource_list;
                $row->save();
            } else {
                $result = $this->createRow();
                $result->user_id = $user_id;
                $result->list_id = $list_id;
                $result->resource_list = $resource_list;
                $result->save();
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get favorite list order
     *
     * @param int $list_id List_id
     * @param int $user_id User_id
     *
     * @return boolean|array
     */
    public function getFavoriteOrder($list_id,$user_id)
    {
        if (! $user_id) {
            if ($result = $this->select(
                ['list_id' => $list_id]
            )->current()) {
                return $result;
            } else {
                return false;
            }
        } else {
            if ($result = $this->select(
                ['list_id' => $list_id, 'user_id' => $user_id]
            )->current()) {
                return $result;
            } else {
                return false;
            }
        }
    }

    /**
     * Get access to the finna_favorite_order table.
     *
     * @return \VuFind\Db\Table\FinnaFavoriteOrder
     */
    public function getFinnaFavoriteOrderTable()
    {
        return $this->getDbTableManager()->get('Finna_favorite_order');
    }
}

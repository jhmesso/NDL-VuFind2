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
     * @param int $user_id  User_id
     * @param int $list_id  List_id
     * @param string $resource_list Ordered List of Resources
     *
     * @return void
     */
    public function saveFavoriteOrder($user_id,$list_id,$resource_list)
    {
        if ($this->select(['user_id' => $user_id, 'list_id' => $list_id])->current()) {
            $this->update(['resource_list' => "$resource_list"], "user_id = $user_id and list_id = $list_id");
        } else {
            $result = $this->createRow();
            $result->user_id = $user_id;
            $result->list_id = $list_id;
            $result->resource_list = $resource_list;
            $result->save();
        }
    }
}

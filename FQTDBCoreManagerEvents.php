<?php

/*
 * This file is part of the FQTDBCoreManagerBundle package.
 *
 * (c) FOUQUET <https://github.com/hugo082/DBManagerBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Hugo Fouquet <hugo.fouquet@epita.fr>
 */

namespace FQT\DBCoreManagerBundle;

/**
 * Contains all events thrown in the DBManagerBundle.
 */
final class FQTDBCoreManagerEvents
{
    /**
     * The ACTION_REMOVE_BEFORE event occurs when the remove action will be called.
     *
     * @Event("DB\ManagerBundle\Event\ActionEvent")
     */
    const ACTION_REMOVE_BEFORE = 'fqtdb_core_manager.action.remove.before';

    /**
     * The ACTION_REMOVE_AFTER event occurs after the remove action have been called.
     *
     * @Event("DB\ManagerBundle\Event\ActionEvent")
     */
    const ACTION_REMOVE_AFTER = 'fqtdb_core_manager.action.remove.after';

    /**
     * The ACTION_EDIT_BEFORE event occurs when the edit action will be called.
     *
     * @Event("DB\ManagerBundle\Event\ActionEvent")
     */
    const ACTION_EDIT_BEFORE = 'fqtdb_core_manager.action.edit.before';

    /**
     * The ACTION_EDIT_AFTER event occurs after the edit action have been called.
     *
     * @Event("DB\ManagerBundle\Event\ActionEvent")
     */
    const ACTION_EDIT_AFTER = 'fqtdb_core_manager.action.edit.after';

    /**
     * The ACTION_ADD_BEFORE event occurs when the add action will be called.
     *
     * @Event("DB\ManagerBundle\Event\ActionEvent")
     */
    const ACTION_ADD_BEFORE = 'fqtdb_core_manager.action.add.before';

    /**
     * The ACTION_ADD_AFTER event occurs after the add action have been called.
     *
     * @Event("DB\ManagerBundle\Event\ActionEvent")
     */
    const ACTION_ADD_AFTER = 'fqtdb_core_manager.action.add.after';
}

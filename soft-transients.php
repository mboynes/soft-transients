<?php
/*
	Plugin Name: Soft Transients
	Plugin URI: https://github.com/mboynes/soft-transients/
	Description: An asynchronous way to refresh transients.
	Version: 0.1
	Author: Matthew Boynes
	Author URI: http://www.alleyinteractive.com/
*/
/*  This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


/**
 * Get a "soft" transient. This is a transient which is updated via wp-cron,
 * so refreshing the transient doesn't slow the request. Otherwise, this works
 * just like get_transient().
 *
 * If the transient does not exist or does not have a value, then the return value
 * will be false.
 *
 * @uses  get_transient
 * @uses  set_transient
 * @uses  wp_schedule_single_event
 *
 * @param string $transient_key Transient name. Expected to not be SQL-escaped.
 * @return mixed Value of transient.
 */
function get_soft_transient( $transient_key ) {
	$transient = get_transient( $transient_key );
	if ( false === $transient ) {
		return false;
	}

	// Ensure that this is a soft transient
	if ( ! is_array( $transient ) || empty( $transient['expiration'] ) || ! array_key_exists( 'data', $transient ) ) {
		return $transient;
	}

	// Check if the transient is expired
	$expiration = intval( $transient['expiration'] );
	if ( ! empty( $expiration ) && $expiration <= time() ) {
		// Cache needs to be updated
		if ( ! empty( $transient['status'] ) && 'ok' == $transient['status'] ) {
			if ( ! empty( $transient['action'] ) ) {
				$action = $transient['action'];
			} else {
				$action = 'transient_refresh_' . $transient_key;
			}

			// Schedule the update action
			wp_schedule_single_event( time(), $action, array( $transient_key ) );
			$transient['status'] = 'loading';

			// Update the transient to indicate that we've scheduled a reload
			set_transient( $transient_key, $transient );
		}
	}

	return $transient['data'];
}


/**
 * Set/update the value of a "soft" transient. This is a transient that, when
 * it expires, will continue to return the value and refresh via wp-cron.
 *
 * You do not need to serialize values. If the value needs to be serialized, then
 * it will be serialized before it is set.
 *
 * @uses set_transient
 *
 * @param string $transient_key Transient name. Expected to not be SQL-escaped.
 * @param mixed $value Transient value. Must be serializable if non-scalar. Expected to not be SQL-escaped.
 * @param int $expiration Optional. Time until expiration in seconds, default 0
 * @param string $action Optional. The action to fire during wp-cron. Defaults
 *                       to `"transient_refresh_{$transient_key}"`.
 * @return bool False if value was not set and true if value was set.
 */
function set_soft_transient( $transient_key, $value, $expiration = 0, $action = null ) {
	if ( ! $expiration ) {
		return set_transient( $transient_key, $value );
	}
	$data = array(
		'expiration' => $expiration + time(),
		'data'       => $value,
		'status'     => 'ok',
		'action'     => $action
	);

	return set_transient( $transient_key, $data );
}


/**
 * Delete a "soft" transient. Will also unschedule the reload event if one is
 * in queue.
 *
 * @param string $transient_key Transient name. Expected to not be SQL-escaped.
 * @param string $action Optional. The action fired during wp-cron. Defaults to
 *                       `"transient_refresh_{$transient_key}"`.
 * @return bool true if successful, false otherwise
 */
function delete_soft_transient( $transient_key, $action = null ) {
	if ( empty( $action ) ) {
		$action = 'transient_refresh_' . $transient_key;
	}

	if ( $timestamp = wp_next_scheduled( $action, array( $transient_key ) ) ) {
		wp_unschedule_event( $timestamp, $action, array( $transient_key ) );
	}

	return delete_transient( $transient_key );
}
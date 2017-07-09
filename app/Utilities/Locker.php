<?php

namespace empleadoEstatalBot\Utilities;

use empleadoEstatalBot\empleadoEstatal;

class Locker
{
    /**
     * checkLock
     *
     * Creates a lock file in the tmp folder to keep track of which workers are running and avoid running them twice
     * at the same time (which could lead to race conditions with the status of posts)
     *
     * @param $worker string Worker to get the lock
     *
     * @return bool Whether the worker is allowed or not to run
     */
    static public function checkLock($worker)
    {
        $lockFile = empleadoEstatal::TMP_DIR . $worker . '.lock';

        if (file_exists($lockFile)) {
            /*
             * Lock exists, check if it isn't too old. (30 minutes max)
             */
            if (filemtime($lockFile) < time() - 60 * 10) {
                /*
                 * Something went horribly wrong (like a segfault or an uncaught exception) and the lock file wasn't
                 * deleted. Remove the file, allow the worker to run and pray to god that the script isn't stuck
                 * in another process.
                 */

                if (unlink($lockFile) === false || file_put_contents($lockFile, '') === false) {
                    /*
                     * WHAT'S WRONG WITH THIS SYSTEM??
                     */
                    empleadoEstatal::$log->addEmergency('LockerUtility: Error while trying to delete/create an old lock file. Check for errors on the log and php error log');
                };

                /*
                 * Allow the worker to run
                 */
                return true;
            }

            /*
             * Lock exists and it's recent, stop the worker
             */

            return false;
        } else {
            /*
             * No lock file, create one and allow the worker to run
             */

            if (file_put_contents($lockFile, '') === false) {
                /*
                 * Something happened while writing the lock file, log the error
                 */
                empleadoEstatal::$log->addEmergency('LockerUtility: Error while trying to create lock file.');
            }

            return true;
        }
    }

    /**
     * releaseLock
     *
     * Releases the lock set before running
     *
     * @param $worker string Worker to release the lock
     *
     * @return bool Success or failure while releasing the lock
     */
    static public function releaseLock($worker)
    {
        $lockFile = empleadoEstatal::TMP_DIR . $worker . '.lock';

        if (unlink($lockFile) === false) {
            empleadoEstatal::$log->addEmergency('LockerUtility: Error while trying to delete lock file on release.');

            return false;
        };

        return true;
    }

    /**
     * clearLocks
     *
     * Clears all locks.
     *
     * @return true
     */
    static public function clearLocks()
    {
        $result = true;
        foreach (glob(empleadoEstatal::TMP_DIR . '*.lock') as $file) {
            if (unlink($file) === false) {
                empleadoEstatal::$log->addEmergency(sprintf('LockerUtility: Error while trying to delete lock file on clear locks. File %s', $file));
                $result = false;
            };
        }

        return $result;
    }

    /**
     * clearLock
     *
     * Clears specific lock.
     *
     * @return true
     */
    static public function clearLock($worker)
    {
        $lockFile = empleadoEstatal::TMP_DIR . $worker . '.lock';

        if (unlink($lockFile) === false) {
            empleadoEstatal::$log->addEmergency(sprintf('LockerUtility: Error while trying to delete lock file on clear specific lock. File %s', $lockFile));

            return false;
        };

        return true;
    }
}

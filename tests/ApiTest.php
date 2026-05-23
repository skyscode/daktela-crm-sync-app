<?php
/**
 * tests/ApiTest.php — Model layer unit/integration tests
 *
 * Tests the Contact, Ticket, and Status model query methods in isolation.
 * Never touches the real MySQL DB — uses an in-memory SQLite PDO
 * instance injected via Database::setInstance() before each test.
 */

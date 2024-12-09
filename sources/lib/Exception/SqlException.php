<?php
/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Exception;

use PgSql\Result;

/**
 * Errors from the rdbms with the result resource.
 *
 * @link      https://www.postgresql.org/docs/current/errcodes-appendix.html
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT <hubert.greg@gmail.com>
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class SqlException extends FoundationException
{
    /* 00 - Successful Completion */
    final const string SUCCESSFUL_COMPLETION = '00000';
    /* 01 - Warning */
    final const string WARNING = '01000';
    final const string DYNAMIC_RESULT_SETS_RETURNED = '0100C';
    final const string IMPLICIT_ZERO_BIT_PADDING = '01008';
    final const string NULL_VALUE_ELIMINATED_IN_SET_FUNCTION = '01003';
    final const string PRIVILEGE_NOT_GRANTED = '01007';
    final const string PRIVILEGE_NOT_REVOKED = '01006';
    final const string STRING_DATA_RIGHT_TRUNCATION = '01004';
    final const string DEPRECATED_FEATURE = '01P01';
    /* 02 - No Data (this is also a warning class per the SQL standard) */
    final const string NO_DATA = '02000';
    final const string NO_ADDITIONAL_DYNAMIC_RESULT_SETS_RETURNED = '02001';
    /* 03 - SQL Statement Not Yet Complete */
    final const string SQL_STATEMENT_NOT_YET_COMPLETE = '03000';
    /* 08 - Connection Exception */
    final const string CONNECTION_EXCEPTION = '08000';
    final const string CONNECTION_DOES_NOT_EXIST = '08003';
    final const string CONNECTION_FAILURE = '08006';
    final const string SQLCLIENT_UNABLE_TO_ESTABLISH_SQLCONNECTION = '08001';
    final const string SQLSERVER_REJECTED_ESTABLISHMENT_OF_SQLCONNECTION = '08004';
    final const string TRANSACTION_RESOLUTION_UNKNOWN = '08007';
    final const string PROTOCOL_VIOLATION = '08P01';
    /* 09 - Triggered Action Exception */
    final const string TRIGGERED_ACTION_EXCEPTION = '09000';
    /* 0A - Feature Not Supported */
    final const string FEATURE_NOT_SUPPORTED = '0A000';
    /* 0B - Invalid Transaction Initiation */
    final const string INVALID_TRANSACTION_INITIATION = '0B000';
    /* 0F - Locator Exception */
    final const string LOCATOR_EXCEPTION = '0F000';
    final const string INVALID_LOCATOR_SPECIFICATION = '0F001';
    /* 0L - Invalid Grantor */
    final const string INVALID_GRANTOR = '0L000';
    final const string INVALID_GRANT_OPERATION = '0LP01';
    /* 0P - Invalid Role Specification */
    final const string INVALID_ROLE_SPECIFICATION = '0P000';
    /* 0Z - Diagnostics Exception */
    final const string DIAGNOSTICS_EXCEPTION = '0Z000';
    final const string STACKED_DIAGNOSTICS_ACCESSED_WITHOUT_ACTIVE_HANDLER = '0Z002';
    /* 20 - Case Not Found */
    final const string CASE_NOT_FOUND = '20000';
    /* 21 - Cardinality Violation */
    final const string CARDINALITY_VIOLATION = '21000';
    /* 22 - Data Exception */
    final const string DATA_EXCEPTION = '22000';
    final const string ARRAY_SUBSCRIPT_ERROR = '2202E';
    final const string CHARACTER_NOT_IN_REPERTOIRE = '22021';
    final const string DATETIME_FIELD_OVERFLOW = '22008';
    final const string DIVISION_BY_ZERO = '22012';
    final const string ERROR_IN_ASSIGNMENT = '22005';
    final const string ESCAPE_CHARACTER_CONFLICT = '2200B';
    final const string INDICATOR_OVERFLOW = '22022';
    final const string INTERVAL_FIELD_OVERFLOW = '22015';
    final const string INVALID_ARGUMENT_FOR_LOGARITHM = '2201E';
    final const string INVALID_ARGUMENT_FOR_NTILE_FUNCTION = '22014';
    final const string INVALID_ARGUMENT_FOR_NTH_VALUE_FUNCTION = '22016';
    final const string INVALID_ARGUMENT_FOR_POWER_FUNCTION = '2201F';
    final const string INVALID_ARGUMENT_FOR_WIDTH_BUCKET_FUNCTION = '2201G';
    final const string INVALID_CHARACTER_VALUE_FOR_CAST = '22018';
    final const string INVALID_DATETIME_FORMAT = '22007';
    final const string INVALID_ESCAPE_CHARACTER = '22019';
    final const string INVALID_ESCAPE_OCTET = '2200D';
    final const string INVALID_ESCAPE_SEQUENCE = '22025';
    final const string NONSTANDARD_USE_OF_ESCAPE_CHARACTER = '22P06';
    final const string INVALID_INDICATOR_PARAMETER_VALUE = '22010';
    final const string INVALID_PARAMETER_VALUE = '22023';
    final const string INVALID_PRECEDING_OR_FOLLOWING_SIZE = '22013';
    final const string INVALID_REGULAR_EXPRESSION = '2201B';
    final const string INVALID_ROW_COUNT_IN_LIMIT_CLAUSE = '2201W';
    final const string INVALID_ROW_COUNT_IN_RESULT_OFFSET_CLAUSE = '2201X';
    final const string INVALID_TABLESAMPLE_ARGUMENT = '2202H';
    final const string INVALID_TABLESAMPLE_REPEAT = '2202G';
    final const string INVALID_TIME_ZONE_DISPLACEMENT_VALUE = '22009';
    final const string INVALID_USE_OF_ESCAPE_CHARACTER = '2200C';
    final const string MOST_SPECIFIC_TYPE_MISMATCH = '2200G';
    final const string NULL_VALUE_NOT_ALLOWED = '22004';
    final const string NULL_VALUE_NO_INDICATOR_PARAMETER = '22002';
    final const string NUMERIC_VALUE_OUT_OF_RANGE = '22003';
    final const string SEQUENCE_GENERATOR_LIMIT_EXCEEDED = '2200H';
    final const string STRING_DATA_LENGTH_MISMATCH = '22026';
    #const STRING_DATA_RIGHT_TRUNCATION = '22001';
    final const string SUBSTRING_ERROR = '22011';
    final const string TRIM_ERROR = '22027';
    final const string UNTERMINATED_C_STRING = '22024';
    final const string ZERO_LENGTH_CHARACTER_STRING = '2200F';
    final const string FLOATING_POINT_EXCEPTION = '22P01';
    final const string INVALID_TEXT_REPRESENTATION = '22P02';
    final const string INVALID_BINARY_REPRESENTATION = '22P03';
    final const string BAD_COPY_FILE_FORMAT = '22P04';
    final const string UNTRANSLATABLE_CHARACTER = '22P05';
    final const string NOT_AN_XML_DOCUMENT = '2200L';
    final const string INVALID_XML_DOCUMENT = '2200M';
    final const string INVALID_XML_CONTENT = '2200N';
    final const string INVALID_XML_COMMENT = '2200S';
    final const string INVALID_XML_PROCESSING_INSTRUCTION = '2200T';
    final const string DUPLICATE_JSON_OBJECT_KEY_VALUE = '22030';
    final const string INVALID_ARGUMENT_FOR_SQL_JSON_DATETIME_FUNCTION = '22031';
    final const string INVALID_JSON_TEXT = '22032';
    final const string INVALID_SQL_JSON_SUBSCRIPT = '22033';
    final const string MORE_THAN_ONE_SQL_JSON_ITEM = '22034';
    final const string NO_SQL_JSON_ITEM = '22035';
    final const string NON_NUMERIC_SQL_JSON_ITEM = '22036';
    final const string NON_UNIQUE_KEYS_IN_A_JSON_OBJECT = '22037';
    final const string SINGLETON_SQL_JSON_ITEM_REQUIRED = '22038';
    final const string SQL_JSON_ARRAY_NOT_FOUND = '22039';
    final const string SQL_JSON_MEMBER_NOT_FOUND = '2203A';
    final const string SQL_JSON_NUMBER_NOT_FOUND = '2203B';
    final const string SQL_JSON_OBJECT_NOT_FOUND = '2203C';
    final const string TOO_MANY_JSON_ARRAY_ELEMENTS = '2203D';
    final const string TOO_MANY_JSON_OBJECT_MEMBERS = '2203E';
    final const string SQL_JSON_SCALAR_REQUIRED = '2203F';
    /* 23 - Integrity Constraint Violation */
    final const string INTEGRITY_CONSTRAINT_VIOLATION = '23000';
    final const string RESTRICT_VIOLATION = '23001';
    final const string NOT_NULL_VIOLATION = '23502';
    final const string FOREIGN_KEY_VIOLATION = '23503';
    final const string UNIQUE_VIOLATION = '23505';
    final const string CHECK_VIOLATION = '23514';
    final const string EXCLUSION_VIOLATION = '23P01';
    /* 24 - Invalid Cursor State */
    final const string INVALID_CURSOR_STATE = '24000';
    /* 25 - Invalid Transaction State */
    final const string INVALID_TRANSACTION_STATE = '25000';
    final const string ACTIVE_SQL_TRANSACTION = '25001';
    final const string BRANCH_TRANSACTION_ALREADY_ACTIVE = '25002';
    final const string HELD_CURSOR_REQUIRES_SAME_ISOLATION_LEVEL = '25008';
    final const string INAPPROPRIATE_ACCESS_MODE_FOR_BRANCH_TRANSACTION = '25003';
    final const string INAPPROPRIATE_ISOLATION_LEVEL_FOR_BRANCH_TRANSACTION = '25004';
    final const string NO_ACTIVE_SQL_TRANSACTION_FOR_BRANCH_TRANSACTION = '25005';
    final const string READ_ONLY_SQL_TRANSACTION = '25006';
    final const string SCHEMA_AND_DATA_STATEMENT_MIXING_NOT_SUPPORTED = '25007';
    final const string NO_ACTIVE_SQL_TRANSACTION = '25P01';
    final const string IN_FAILED_SQL_TRANSACTION = '25P02';
    final const string IDLE_IN_TRANSACTION_SESSION_TIMEOUT = '25P03';
    /* 26 - Invalid SQL Statement Name */
    final const string INVALID_SQL_STATEMENT_NAME = '26000';
    /* 27 - Triggered Data Change Violation */
    final const string TRIGGERED_DATA_CHANGE_VIOLATION = '27000';
    /* 28 - Invalid Authorization Specification */
    final const string INVALID_AUTHORIZATION_SPECIFICATION = '28000';
    final const string INVALID_PASSWORD = '28P01';
    /* 2B - Dependent Privilege Descriptors Still Exist */
    final const string DEPENDENT_PRIVILEGE_DESCRIPTORS_STILL_EXIST = '2B000';
    final const string DEPENDENT_OBJECTS_STILL_EXIST = '2BP01';
    /* 2D - Invalid Transaction Termination */
    final const string INVALID_TRANSACTION_TERMINATION = '2D000';
    /* 2F - SQL Routine Exception */
    final const string SQL_ROUTINE_EXCEPTION = '2F000';
    final const string FUNCTION_EXECUTED_NO_RETURN_STATEMENT = '2F005';
    final const string MODIFYING_SQL_DATA_NOT_PERMITTED = '2F002';
    final const string PROHIBITED_SQL_STATEMENT_ATTEMPTED = '2F003';
    final const string READING_SQL_DATA_NOT_PERMITTED = '2F004';
    /* 34 - Invalid Cursor Name */
    final const string INVALID_CURSOR_NAME = '34000';
    /* 38 - External Routine Exception */
    final const string EXTERNAL_ROUTINE_EXCEPTION = '38000';
    final const string CONTAINING_SQL_NOT_PERMITTED = '38001';
    #const MODIFYING_SQL_DATA_NOT_PERMITTED = '38002';
    #const PROHIBITED_SQL_STATEMENT_ATTEMPTED = '38003';
    #const READING_SQL_DATA_NOT_PERMITTED = '38004';
    /* 39 - External Routine Invocation Exception */
    final const string EXTERNAL_ROUTINE_INVOCATION_EXCEPTION = '39000';
    final const string INVALID_SQLSTATE_RETURNED = '39001';
    #const NULL_VALUE_NOT_ALLOWED = '39004';
    final const string TRIGGER_PROTOCOL_VIOLATED = '39P01';
    final const string SRF_PROTOCOL_VIOLATED = '39P02';
    final const string EVENT_TRIGGER_PROTOCOL_VIOLATED = '39P03';
    /* 3B - Savepoint Exception */
    final const string SAVEPOINT_EXCEPTION = '3B000';
    final const string INVALID_SAVEPOINT_SPECIFICATION = '3B001';
    /* 3D - Invalid Catalog Name */
    final const string INVALID_CATALOG_NAME = '3D000';
    /* 3F - Invalid Schema Name */
    final const string INVALID_SCHEMA_NAME = '3F000';
    /* 40 - Transaction Rollback */
    final const string TRANSACTION_ROLLBACK = '40000';
    final const string TRANSACTION_INTEGRITY_CONSTRAINT_VIOLATION = '40002';
    final const string SERIALIZATION_FAILURE = '40001';
    final const string STATEMENT_COMPLETION_UNKNOWN = '40003';
    final const string DEADLOCK_DETECTED = '40P01';
    /* 42 - Syntax Error or Access Rule Violation */
    final const string SYNTAX_ERROR_OR_ACCESS_RULE_VIOLATION = '42000';
    final const string SYNTAX_ERROR = '42601';
    final const string INSUFFICIENT_PRIVILEGE = '42501';
    final const string CANNOT_COERCE = '42846';
    final const string GROUPING_ERROR = '42803';
    final const string WINDOWING_ERROR = '42P20';
    final const string INVALID_RECURSION = '42P19';
    final const string INVALID_FOREIGN_KEY = '42830';
    final const string INVALID_NAME = '42602';
    final const string NAME_TOO_LONG = '42622';
    final const string RESERVED_NAME = '42939';
    final const string DATATYPE_MISMATCH = '42804';
    final const string INDETERMINATE_DATATYPE = '42P18';
    final const string COLLATION_MISMATCH = '42P21';
    final const string INDETERMINATE_COLLATION = '42P22';
    final const string WRONG_OBJECT_TYPE = '42809';
    final const string GENERATED_ALWAYS = '428C9';
    final const string UNDEFINED_COLUMN = '42703';
    final const string UNDEFINED_FUNCTION = '42883';
    final const string UNDEFINED_TABLE = '42P01';
    final const string UNDEFINED_PARAMETER = '42P02';
    final const string UNDEFINED_OBJECT = '42704';
    final const string DUPLICATE_COLUMN = '42701';
    final const string DUPLICATE_CURSOR = '42P03';
    final const string DUPLICATE_DATABASE = '42P04';
    final const string DUPLICATE_FUNCTION = '42723';
    final const string DUPLICATE_PREPARED_STATEMENT = '42P05';
    final const string DUPLICATE_SCHEMA = '42P06';
    final const string DUPLICATE_TABLE = '42P07';
    final const string DUPLICATE_ALIAS = '42712';
    final const string DUPLICATE_OBJECT = '42710';
    final const string AMBIGUOUS_COLUMN = '42702';
    final const string AMBIGUOUS_FUNCTION = '42725';
    final const string AMBIGUOUS_PARAMETER = '42P08';
    final const string AMBIGUOUS_ALIAS = '42P09';
    final const string INVALID_COLUMN_REFERENCE = '42P10';
    final const string INVALID_COLUMN_DEFINITION = '42611';
    final const string INVALID_CURSOR_DEFINITION = '42P11';
    final const string INVALID_DATABASE_DEFINITION = '42P12';
    final const string INVALID_FUNCTION_DEFINITION = '42P13';
    final const string INVALID_PREPARED_STATEMENT_DEFINITION = '42P14';
    final const string INVALID_SCHEMA_DEFINITION = '42P15';
    final const string INVALID_TABLE_DEFINITION = '42P16';
    final const string INVALID_OBJECT_DEFINITION = '42P17';
    /* 44 - WITH CHECK OPTION Violation */
    final const string WITH_CHECK_OPTION_VIOLATION = '44000';
    /* 53 - Insufficient Resources */
    final const string INSUFFICIENT_RESOURCES = '53000';
    final const string DISK_FULL = '53100';
    final const string OUT_OF_MEMORY = '53200';
    final const string TOO_MANY_CONNECTIONS = '53300';
    final const string CONFIGURATION_LIMIT_EXCEEDED = '53400';
    /* 54 - Program Limit Exceeded */
    final const string PROGRAM_LIMIT_EXCEEDED = '54000';
    final const string STATEMENT_TOO_COMPLEX = '54001';
    final const string TOO_MANY_COLUMNS = '54011';
    final const string TOO_MANY_ARGUMENTS = '54023';
    /* 55 - Object Not In Prerequisite State */
    final const string OBJECT_NOT_IN_PREREQUISITE_STATE = '55000';
    final const string OBJECT_IN_USE = '55006';
    final const string CANT_CHANGE_RUNTIME_PARAM = '55P02';
    final const string LOCK_NOT_AVAILABLE = '55P03';
    final const string UNSAFE_NEW_ENUM_VALUE_USAGE = '55P04';
    /* 57 - Operator Intervention */
    final const string OPERATOR_INTERVENTION = '57000';
    final const string QUERY_CANCELED = '57014';
    final const string ADMIN_SHUTDOWN = '57P01';
    final const string CRASH_SHUTDOWN = '57P02';
    final const string CANNOT_CONNECT_NOW = '57P03';
    final const string DATABASE_DROPPED = '57P04';
    /* 58 - System Error (errors external to PostgreSQL itself) */
    final const string SYSTEM_ERROR = '58000';
    final const string IO_ERROR = '58030';
    final const string UNDEFINED_FILE = '58P01';
    final const string DUPLICATE_FILE = '58P02';
    /* F0 - Configuration File Error */
    final const string CONFIG_FILE_ERROR = 'F0000';
    final const string LOCK_FILE_EXISTS = 'F0001';
    /* HV - Foreign Data Wrapper Error (SQL/MED) */
    final const string FDW_ERROR = 'HV000';
    final const string FDW_COLUMN_NAME_NOT_FOUND = 'HV005';
    final const string FDW_DYNAMIC_PARAMETER_VALUE_NEEDED = 'HV002';
    final const string FDW_FUNCTION_SEQUENCE_ERROR = 'HV010';
    final const string FDW_INCONSISTENT_DESCRIPTOR_INFORMATION = 'HV021';
    final const string FDW_INVALID_ATTRIBUTE_VALUE = 'HV024';
    final const string FDW_INVALID_COLUMN_NAME = 'HV007';
    final const string FDW_INVALID_COLUMN_NUMBER = 'HV008';
    final const string FDW_INVALID_DATA_TYPE = 'HV004';
    final const string FDW_INVALID_DATA_TYPE_DESCRIPTORS = 'HV006';
    final const string FDW_INVALID_DESCRIPTOR_FIELD_IDENTIFIER = 'HV091';
    final const string FDW_INVALID_HANDLE = 'HV00B';
    final const string FDW_INVALID_OPTION_INDEX = 'HV00C';
    final const string FDW_INVALID_OPTION_NAME = 'HV00D';
    final const string FDW_INVALID_STRING_LENGTH_OR_BUFFER_LENGTH = 'HV090';
    final const string FDW_INVALID_STRING_FORMAT = 'HV00A';
    final const string FDW_INVALID_USE_OF_NULL_POINTER = 'HV009';
    final const string FDW_TOO_MANY_HANDLES = 'HV014';
    final const string FDW_OUT_OF_MEMORY = 'HV001';
    final const string FDW_NO_SCHEMAS = 'HV00P';
    final const string FDW_OPTION_NAME_NOT_FOUND = 'HV00J';
    final const string FDW_REPLY_HANDLE = 'HV00K';
    final const string FDW_SCHEMA_NOT_FOUND = 'HV00Q';
    final const string FDW_TABLE_NOT_FOUND = 'HV00R';
    final const string FDW_UNABLE_TO_CREATE_EXECUTION = 'HV00L';
    final const string FDW_UNABLE_TO_CREATE_REPLY = 'HV00M';
    final const string FDW_UNABLE_TO_ESTABLISH_CONNECTION = 'HV00N';
    /* P0 - PL/pgSQL Error */
    final const string PLPGSQL_ERROR = 'P0000';
    final const string RAISE_EXCEPTION = 'P0001';
    final const string NO_DATA_FOUND = 'P0002';
    final const string TOO_MANY_ROWS = 'P0003';
    final const string ASSERT_FAILURE = 'P0004';
    /* XX - Internal Error */
    final const string INTERNAL_ERROR = 'XX000';
    final const string DATA_CORRUPTED = 'XX001';
    final const string INDEX_CORRUPTED = 'XX002';

    /** @var array<int, string> */
    protected array $queryParameters = [];

    public function __construct(protected Result $result, protected string $sql, int $code = 0, ?\Exception $e = null)
    {
        parent::__construct(
            sprintf(
                "\nSQL error state '%s' [%s]\n====\n%s\n====\n«%s».",
                $this->getSQLErrorState(),
                $this->getSQLErrorSeverity(),
                $this->getSqlErrorMessage(),
                $sql
            ),
            $code,
            $e
        );
    }

    /**
     * Returns the SQLSTATE of the last SQL error.
     *
     * @link http://www.postgresql.org/docs/9.0/interactive/errcodes-appendix.html
     */
    public function getSQLErrorState(): string
    {
        return pg_result_error_field($this->result, \PGSQL_DIAG_SQLSTATE);
    }

    /** Returns the severity level of the error. */
    public function getSQLErrorSeverity(): string
    {
        return pg_result_error_field($this->result, \PGSQL_DIAG_SEVERITY);
    }

    /** Returns the error message sent by the server. */
    public function getSqlErrorMessage(bool $primary = false): string
    {
        return $primary ?
            pg_result_error_field($this->result, \PGSQL_DIAG_MESSAGE_PRIMARY) : pg_result_error($this->result);
    }

    public function getSQLDetailedErrorMessage(): string
    {
        return sprintf(
            "«%s»\n%s\n(%s)",
            pg_result_error_field($this->result, \PGSQL_DIAG_MESSAGE_PRIMARY),
            pg_result_error_field($this->result, \PGSQL_DIAG_MESSAGE_DETAIL),
            pg_result_error_field($this->result, \PGSQL_DIAG_MESSAGE_HINT)
        );
    }

    /** Return the associated query. */
    public function getQuery(): string
    {
        return $this->sql;
    }

    /**
     * Return the query parameters sent with the query.
     *
     * @return array<int, string>
     */
    public function getQueryParameters(): array
    {
        return $this->queryParameters;
    }

    /**
     * Set the query parameters sent with the query.
     *
     * @param array<int, string> $parameters
     * @return SqlException $this
     */
    public function setQueryParameters(array $parameters): SqlException
    {
        $this->queryParameters = $parameters;

        return $this;
    }
}

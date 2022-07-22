<?php

namespace PommProject\Foundation\PreparedQuery;

enum PreparationEnum: string{
    case NOT_PREPARED = 'not prepared';
    case IN_PREPARATION = 'in preparation';
    case PREPARED = 'prepared';
}
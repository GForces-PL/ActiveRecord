<?php


namespace Gforces\ActiveRecord;

enum ValidationContext {
    case always;
    case onCreate;
    case onUpdate;
}

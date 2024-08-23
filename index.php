<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
include 'includes/settings.php';

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$method = $_SERVER['REQUEST_METHOD'];
$url = parse_url($_SERVER['REQUEST_URI']);
$path = $url['path'];
$pathParts = explode('/', $path);

// Установка заголовков HTTP для продакшена вместо https://tasks.nickdev.ru нужно написать урл клиентского сайта
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: https://tasks.nickdev.ru');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT, PATCH');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Credentials: true');

function clean($data) {
    global $conn;
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = mysqli_real_escape_string($conn, htmlspecialchars(strip_tags($value)));
        }
        $data = serialize($data);
    } else {
        if(is_bool($data)){
            $data = $data?1:0;
        }
        $data = mysqli_real_escape_string($conn, htmlspecialchars(strip_tags($data)));
    }
    return $data;
}

function buildFilter($params) {
    $filter = 'WHERE ';
    $filters = [];
    foreach ($params as $key => $value) {

        $parts = explode('_', $key);
        $field = $parts[0];
        $operator = isset($parts[1]) ? $parts[1] : 'eq'; 

        // Обработка специальных операторов
        switch ($operator) {
            case 'ne':
                $filters[] = "$field != '" . clean($value) . "'";
                break;
            case 'lt':
                $filters[] = "$field < '" . clean($value) . "'";
                break;
            case 'gt':
                $filters[] = "$field > '" . clean($value) . "'";
                break;
            case 'lte':
                $filters[] = "$field <= '" . clean($value) . "'";
                break;
            case 'gte':
                $filters[] = "$field >= '" . clean($value) . "'";
                break;
            case 'like':
                $filters[] = "$field LIKE '%" . clean($value) . "%'";
                break;
            default:
                $filters[] = "$field = '" . clean($value) . "'";
                break;
        }
    }
    $filter .= implode(' AND ', $filters);
    return $filter;
}

function buildInsertQuery($table, $data) {
    global $conn;

    $keys = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));

    $sql = "INSERT INTO $table ($keys) VALUES ($placeholders)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Ошибка при подготовке запроса: " . $conn->error);
    }

    $types = str_repeat('s', count($data));
    $values = array_values($data);
    $stmt->bind_param($types, ...$values);
    return $stmt;
}

function buildUpdateQuery($table, $id, $data) {
    global $conn;

    $updates = [];
    foreach ($data as $key => $value) {
        $updates[] = "$key = ?";
    }

    $setClause = implode(', ', $updates);
    $sql = "UPDATE $table SET $setClause WHERE id = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Ошибка при подготовке запроса: " . $conn->error);
    }

    $types = str_repeat('s', count($data)) . 'i';
    $values = array_values($data);
    $values[] = $id;
    $stmt->bind_param($types, ...$values);
    return $stmt;
}

function buildPatchQuery($table, $id, $data) {
    global $conn;

    $updates = [];
    foreach ($data as $key => $value) {
        $updates[] = "$key = ?";
    }

    $setClause = implode(', ', $updates);
    $sql = "UPDATE $table SET $setClause WHERE id = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Ошибка при подготовке запроса: " . $conn->error);
    }

    $types = str_repeat('s', count($data)) . 'i'; 
    $values = array_values($data);
    $values[] = $id;
    $stmt->bind_param($types, ...$values);
    return $stmt;
}

switch ($method) {
    case 'GET':
        $table = $pathParts[1] === 'api'? $pathParts[2]:$pathParts[1];
        if (empty($table)) {
            http_response_code(404);
            echo json_encode(['error' => 'Таблица не указана']);
            exit;
        }

        if ($table == 'users' || $table == 'tasks') {
            $params = $_GET;
            $filter = !empty($params) ? buildFilter($params) : '';

            $sql = "SELECT * FROM $table $filter";

            $result = $conn->query($sql);
            $rows = [];

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    if (array_key_exists('completed', $row)) {
                        $row['completed'] = $row['completed'] == "1" ? true : false;
                    }
                    if (array_key_exists("friends", $row)) {
                        $row['friends'] = unserialize($row['friends']);
                    }
                    $rows[] = $row;
                }
            }

            echo json_encode($rows);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Таблица не найдена']);
        }
        break;

    case 'POST':
        $table = $pathParts[1] === 'api'? $pathParts[2]:$pathParts[1];

        if (empty($table)) {
            http_response_code(404);
            echo json_encode(['error' => 'Таблица не указана']);
            exit;
        }

        if ($table == 'users' || $table == 'tasks') {
            $input = json_decode(file_get_contents('php://input'), true);

            $cleanedInput = [];
            foreach ($input as $key => $value) {
                $cleanedInput[$key] = clean($value);
            }

            $stmt = buildInsertQuery($table, $cleanedInput);

            if ($stmt->execute()) {
                $id = $conn->insert_id;

                $sql = "SELECT * FROM $table WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $id); 

                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        if (array_key_exists('completed', $row)) {
                            $row['completed'] = $row['completed'] == "1" ? true : false;
                        }
                        if (array_key_exists("friends", $row)) {
                            $row['friends'] = unserialize($row['friends']);
                        }
                        echo json_encode($row);
                    } else {
                        http_response_code(500);
                        echo json_encode(['error' => 'Ошибка при получении созданной записи']);
                    }
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Ошибка при выполнении запроса: ' . $stmt->error]);
                }
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Ошибка при выполнении запроса: ' . $stmt->error]);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Таблица не найдена']);
        }
        break;

    case 'PUT':
        $table = $pathParts[1] === 'api'? $pathParts[2]:$pathParts[1];
        if (empty($table)) {
            http_response_code(404);
            echo json_encode(['error' => 'Таблица не указана']);
            exit;
        }

        if ($table == 'users' || $table == 'tasks') {
            $id = clean($pathParts[2]);
            $input = json_decode(file_get_contents('php://input'), true);

            $cleanedInput = [];
            foreach ($input as $key => $value) {
                if ($key == 'completed') {
                    $value = $value ? 1 : 0; 
                }
                $cleanedInput[$key] = clean($value);
            }

            $stmt = buildUpdateQuery($table, $id, $cleanedInput);

            if ($stmt->execute()) {
                $sql = "SELECT * FROM $table WHERE id=$id";
                $result = $conn->query($sql);

                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    if (array_key_exists('completed', $row)) {
                        $row['completed'] = $row['completed'] == "1" ? true : false;
                    }
                    if (array_key_exists("friends", $row)) {
                        $row['friends'] = unserialize($row['friends']);
                    }
                    echo json_encode($row);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Ошибка при получении обновленной записи']);
                }
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Ошибка при выполнении запроса: ' . $conn->error]);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Таблица не найдена']);
        }
        break;

        case 'PATCH':
          $table = $pathParts[1] === 'api'? $pathParts[2]:$pathParts[1];
          if (empty($table)) {
              http_response_code(404);
              echo json_encode(['error' => 'Таблица не указана']);
              exit;
          }
      
          if ($table == 'users' || $table == 'tasks') {
              $id = clean($pathParts[2]);
              $input = json_decode(file_get_contents('php://input'), true);
      
              if (empty($input)) {
                  http_response_code(400);
                  echo json_encode(['error' => 'Нет данных для обновления']);
                  exit;
              }
      
              $cleanedInput = [];
              foreach ($input as $key => $value) {
                  $cleanedInput[$key] = clean($value);
              }
      
              $stmt = buildPatchQuery($table, $id, $cleanedInput);
      
              if ($stmt->execute()) {
                  $sql = "SELECT * FROM $table WHERE id=$id";
                  $result = $conn->query($sql);
      
                  if ($result->num_rows > 0) {
                      $row = $result->fetch_assoc();
                      if (array_key_exists('completed', $row)) {
                          $row['completed'] = $row['completed'] == "1" ? true : false;
                      }
                      if (array_key_exists("friends", $row)) {
                          $row['friends'] = unserialize($row['friends']);
                      }
                      echo json_encode($row);
                  } else {
                      http_response_code(500);
                      echo json_encode(['error' => 'Ошибка при получении обновленной записи']);
                  }
              } else {
                  http_response_code(500);
                  echo json_encode(['error' => 'Ошибка при выполнении запроса: ' . $conn->error]);
              }
          } else {
              http_response_code(404);
              echo json_encode(['error' => 'Таблица не найдена']);
          }
          break;
      

    case 'DELETE':
        $table = $pathParts[1] === 'api'? $pathParts[2]:$pathParts[1];
        if (empty($table)) {
            http_response_code(404);
            echo json_encode(['error' => 'Таблица не указана']);
            exit;
        }

        if ($table == 'users' || $table == 'tasks') {
            $id = clean($pathParts[2]);
            $sql = "DELETE FROM $table WHERE id=$id";

            if ($conn->query($sql) === TRUE) {
                echo json_encode(['id' => $id]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Ошибка при выполнении запроса: ' . $conn->error]);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Таблица не найдена']);
        }
        break;

    case 'OPTIONS':
        header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type");
        header("Access-Control-Allow-Origin: *");
        http_response_code(200);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Метод не поддерживается']);
        break;
}

$conn->close();
?>

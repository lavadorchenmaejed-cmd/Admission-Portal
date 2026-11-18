<?php
class UserController {
    private $model;
    public function __construct(User $model) {
        $this->model = $model;
    }

    // Fetch user by user_Id
    public function getByUserId($user_Id) {
        return $this->model->findById($user_Id); // uses findById from User model
    }

    public function store($username, $password, $email) {
       $newUserId = $this->model->create($username, $password, $email, 'applicant');
        if ($newUserId) {
            if (session_status() === PHP_SESSION_NONE) session_start();
            $_SESSION['user_Id'] = $newUserId;
            return $newUserId;
        }
        return false;
    }

    public function findByUsername($username) {
        return $this->model->findByUsername($username);
    }

    public function updateEmail($user_Id, $email) {
        return $this->model->updateEmail($user_Id, $email); // fixed: was $this->user
    }

    public function login($username, $password) {
        $user = $this->model->verifyCredentials($username, $password);
        if ($user) {
            if (session_status() === PHP_SESSION_NONE) session_start();
            $_SESSION['user_Id']  = $user['user_Id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            if (strtolower(trim($user['role'] ?? '')) !== 'admin') {
                $applicant = $this->model->getApplicantByUserId($user['user_Id']);
                if ($applicant) {
                    $_SESSION['applicant_id'] = $applicant['applicant_id'];
                    $_SESSION['program_id']   = $applicant['program_id'];
                }
            }
            return $user;
        }
        return false;
    }
}

class ProfileController {
    private $model;
    public function __construct(Profile $model) {
        $this->model = $model;
    }

    public function store(
        $Applicant_name, $user_Id, $Father_name, $Mother_name, $birthdate, $gender,
        $contact_number, $address, $religion, $nationality, $photoPath, $student_type, $program_id
    ) {
        if (!$user_Id) die("Error: User must be logged in to create a profile.");
        return $this->model->create(
            $Applicant_name, $user_Id, $Father_name, $Mother_name, $birthdate, $gender,
            $contact_number, $address, $religion, $nationality, $photoPath, $student_type, $program_id
        );
    }

    public function getById($id) {
        return $this->model->findById($id);
    }

    public function getByUserId($user_Id) {
        return $this->model->findByUserId($user_Id);
    }

    public function getByContactNumber($contact_number) {
        return $this->model->findByContactNumber($contact_number); // fixed: was $this->profile
    }

    public function getAll() {
        return $this->model->findAll();
    }

    public function update(
        $Applicant_name, $Father_name, $Mother_name, $birthdate, $gender,
        $contact_number, $address, $religion, $nationality, $id
    ) {
        return $this->model->update(
            $Applicant_name, $Father_name, $Mother_name, $birthdate, $gender,
            $contact_number, $address, $religion, $nationality, $id
        );
    }
}
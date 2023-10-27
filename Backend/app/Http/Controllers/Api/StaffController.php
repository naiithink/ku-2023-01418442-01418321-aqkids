<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\User;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\UserRequest;
use App\Models\Enums\EnrollmentStatusEnum;
use App\Models\Enums\UserRoleEnum;
use App\Models\Timeslot;
use App\Models\Enums\TimeslotTypeEnum;
use App\Models\Enums\StudentAttendanceEnum;
use App\Models\Enums\UserRequestStatusEnum;
use App\Models\Enums\UserRequestTypeEnum;

class StaffController extends Controller
{

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['rejectEnrollment','acceptEnrollment','enrollmentRequestReview'
        ,'allEnrollmentRequests','allTeachers'
        ,'getTeacher','createTeacher','searchTeacher'
        ,'allStudents','getStudent','searchStudent'
        ,'getAllCourses','filterStudent','getCourse'
        ,'allTimeslots','createTimeslot','getTimeslot'
        ,'getTimeslotStudents','addStudentAttendance','removeStudentAttendance'
        ,'enrollmentNotPending','removeTimeslot','allUserRequests'
        ,'allUserRequestHistories','userRequestReview'
        ,'acceptRequest','rejectRequest','getTeacherList']]);
    }

    public function generateTimeslot(Request $request) {

        // 1. สร้าง course ตามเวลา generic

        // 2. assign ครู

        // 3. จอง timeslot specific

    }

    /**
     * 
     *  course open 1 คาบ ไม่มี student (ควรจะปิด)
     */

    public function getAllCourses() {
        return Course::get();
    }


    public function getCourse(Course $course) {

        $courseWithAllTimeslots = Course::allTimeslotsWithAuthor($course);

        $courseWithAllTimeslots->enroll_count = Course::studentsIn($courseWithAllTimeslots->id)->count();
        
        return $courseWithAllTimeslots;
    }

    public function allTimeslots() {
        
        $timeslots = Timeslot::get();

        return Timeslot::queryTimeslotCourseTitle($timeslots);
    }
     

    public function allEnrollmentRequests() {

        $enrollments = Enrollment::enrollmentsWithStatus(EnrollmentStatusEnum::PENDING);
        
        $enrollmentsWithUser = Enrollment::getEnrollmentWithUserPaginate($enrollments);
        
        return $enrollmentsWithUser;

    }

    public function enrollmentRequestReview(Enrollment $enrollment) {
        $enrollment = Enrollment::getEnrollmentWithUser($enrollment);
        return $enrollment;
        // return specific enrollment request

    }

    public function enrollmentNotPending() {

        $enrollments = Enrollment::getEnrollmentNotPending();
        $enrollmentsWithUser = Enrollment::getEnrollmentWithUserPaginate($enrollments);
        return $enrollmentsWithUser;
    }

    public function acceptEnrollment(Enrollment $enrollment,Request $request) {
       
        if ($enrollment->updateStatus(EnrollmentStatusEnum::SUCCESS,$request->get('comment'))) {
        
            return response()->json([
                'message' => "Successfully Accept Enrollment",
            ]);

       }

       return response()->json([
        'message' => "Failed to Accept Enrollment",
        ],422);

    }

    public function rejectEnrollment(Enrollment $enrollment,Request $request) {
        
        if ($enrollment->updateStatus(EnrollmentStatusEnum::FAILED,$request->get('comment'))) {

            return response()->json([
                'message' => "Successfully Reject Enrollment",
            ]);
        
        }

        return response()->json([
            'message' => "Failed to Reject Enrollment",
        ],422);

    }

    public function getTimeslot(Timeslot $timeslot) {

        $timeslot->title = Course::find($timeslot->course_id)->title;

        return $timeslot;

    }

    public function createTimeslot(Course $course, Request $request) {

        $dateTime = $request->get('datetime');

        $statusOk = Timeslot::createTimeslot($course->id, strtotime($dateTime), TimeslotTypeEnum::MAKEUP);

        if ($statusOk) {

            return response()->json([
                'message' => "Successfully Created Timeslot",
            ]);

        }
        
        return response()->json([
            'message' => "Failed to Created Timeslot",
        ],422);
        
    }

    public function removeTimeslot(Timeslot $timeslot) {

        $courseId = $timeslot->course_id;
        
        $statusOk = Timeslot::deleteTimeslot($timeslot->id);

        if ($statusOk) {

            return response()->json([
                'message' => "Successfully Delete Timeslot",
                'course_id' => $courseId
            ]);

        }
        
        return response()->json([
            'message' => "Failed to Deleted Timeslot",
        ],422);
        

    }

    public function getTimeslotStudents(Timeslot $timeslot) {

        return Timeslot::getTimeslotStudents($timeslot);

    }


    public function addStudentAttendance(Timeslot $timeslot, User $student) {

        if ($timeslot->attachStudents(StudentAttendanceEnum::FALSE, $student->id)) {

            return response()->json([
                'message' => "Successfully Added Student",
            ]);

        }

        return response()->json([
            'message' => "Failed to Add Student",
        ],422);

        
    }

    public function removeStudentAttendance(Timeslot $timeslot, User $student) {

        if ($timeslot->detachStudents($student->id)) {

            return response()->json([
                'message' => "Successfully Removed Student",
            ]);

        }

        return response()->json([
            'message' => "Failed to Remove Student",
        ],422);

    }
    
    // Student Page
 
    public function filterStudent(Request $request) {

        if ($request->get('filter') == 'active') {

            $activeStudents = User::queryStudentWithCoursesCountFilter('active');

            return $activeStudents;


        }

        else if ($request->get('filter') == 'inactive') {

            $inactiveStudents = User::queryStudentWithCoursesCountFilter('inactive');

            return $inactiveStudents;


        }

        else {

            $students = User::allWithRolePaginate(UserRoleEnum::STUDENT);

            $studentsWithCoursesCount = User::queryStudentWithCoursesCount($students);

            return $studentsWithCoursesCount;

        }

    }

    public function getStudent(User $user) {

        // implement enrollments futhermore
        $userWithCourses = User::getStudentWithCourses($user);

        return $userWithCourses;

    }

    public function searchStudent(Request $request) {

        $search = $request->input('search');

        $students = User::searchUser($search, UserRoleEnum::STUDENT);

        return $students;

    }


    // Teacher Page

    public function allTeachers() {

        $teachers = User::allWithRolePaginate(UserRoleEnum::TEACHER);

        $teachersWithCourses = User::queryWithCoursesCountPaginate($teachers);

        return $teachersWithCourses;
        // return all Teachers

    }

    public function searchTeacher(Request $request) {

        $search = $request->input('search');

        $teachers = User::searchUser($search, UserRoleEnum::TEACHER);

        $teachersWithCourses = User::queryWithCoursesCountCollection($teachers);

        return $teachersWithCourses;

    }

    public function getTeacher(User $user) {

        return User::queryTeacherWithCourses($user);

    }

    public function getTeacherList(){
        $teachers = User::allWithRole(UserRoleEnum::TEACHER);
        return $teachers;
    }

    public function createTeacher(Request $request) {

        $request->validate([
            'username' => 'required|max:255|unique:users',
            'password' => 'required|confirmed|min:6',
            'firstname' => 'required',
            'middlename' => 'nullable',
            'lastname' => 'required',
            'birthdate' => 'required',
            'phone_number' => 'required',
            'email' => 'nullable',
            'profile_image_path' => 'nullable|image|mimes:png,gif,jpg,jpeg,bmp|max:2048'
        ]);
        
        $statusOk = User::createUser($request->get('username'), $request->get('password'), UserRoleEnum::TEACHER,
                                     $request->get('firstname'), $request->get('middlename'), $request->get('lastname'),
                                     $request->get('birthdate'), $request->get('phone_number'), $request->get('email'),
                                     $request->file('profile_image_path'));

        

        if ( $statusOk != false ) {

            return response()->json([
                'message' => "Successfully created User",
            ]);

        }

        return response()->json([
            'message' => "Failed to create User",
        ],422);

        
    }




    // refund

    public function userRequestReview(UserRequest $userRequest) {

        $userRequestWithUser = UserRequest::getUserRequestWithUser($userRequest);

        return $userRequestWithUser;

    }

    public function allUserRequests() {

        $allUserRequests = UserRequest::getUserRequests();

        $allUserRequestsWithUser = UserRequest::getUserRequestsWithUserPaginate($allUserRequests);

        return $allUserRequestsWithUser;

    }

    public function allUserRequestHistories() {

        $allUserRequests = UserRequest::getUserRequestHistories();

        $allUserRequestsWithUser = UserRequest::getUserRequestsWithUserPaginate($allUserRequests);

        return $allUserRequestsWithUser;

    }

    public function acceptRequest(UserRequest $userRequest,Request $request) {
       
        if ($userRequest->updateStatus(UserRequestStatusEnum::APPROVED,$request->get('comment'))) {
        
            return response()->json([
                'message' => "Successfully Approved UserRequest",
            ]);

       }

       return response()->json([
        'message' => "Failed to Approve UserRequest",
        ],422);

    }

    public function rejectRequest(UserRequest $userRequest,Request $request) {
        
        if ($userRequest->updateStatus(UserRequestStatusEnum::REJECTED,$request->get('comment'))) {

            return response()->json([
                'message' => "Successfully Rejected UserRequest",
            ]);
        
        }

        return response()->json([
            'message' => "Failed to Reject UserRequest",
        ],422);

    }
}

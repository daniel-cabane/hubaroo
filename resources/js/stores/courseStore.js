import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useCourseStore = defineStore('course', () => {
  const courses = ref([]);
  const course = ref(null);
  const isLoading = ref(false);
  const error = ref(null);

  async function fetchCourses(divisionId) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.get(`/api/divisions/${divisionId}/courses`);
      courses.value = response.data.courses;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch courses';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function createCourse(divisionId, title) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.post(`/api/divisions/${divisionId}/courses`, { title });
      courses.value.unshift(response.data.course);
      return response.data.course;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create course';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function updateCourse(courseId, data) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.patch(`/api/courses/${courseId}`, data);
      const idx = courses.value.findIndex(c => c.id === courseId);
      if (idx !== -1) {
        courses.value[idx] = response.data.course;
      }
      if (course.value?.id === courseId) {
        course.value = response.data.course;
      }
      return response.data.course;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update course';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function deleteCourse(courseId) {
    isLoading.value = true;
    error.value = null;
    try {
      await axios.delete(`/api/courses/${courseId}`);
      courses.value = courses.value.filter(c => c.id !== courseId);
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete course';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function fetchCourseDetails(courseId) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.get(`/api/courses/${courseId}/details`);
      course.value = response.data.course;
      return response.data;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch course details';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function createJump(courseId, data) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.post(`/api/courses/${courseId}/jumps`, data);
      return response.data.jump;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create jump';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function updateJump(jumpId, data) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await axios.patch(`/api/jumps/${jumpId}`, data);
      return response.data.jump;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update jump';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function deleteJump(jumpId) {
    isLoading.value = true;
    error.value = null;
    try {
      await axios.delete(`/api/jumps/${jumpId}`);
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete jump';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  return {
    courses,
    course,
    isLoading,
    error,
    fetchCourses,
    createCourse,
    updateCourse,
    deleteCourse,
    fetchCourseDetails,
    createJump,
    updateJump,
    deleteJump,
  };
});

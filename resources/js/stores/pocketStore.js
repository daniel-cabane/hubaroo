import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import axios from 'axios';

export const usePocketStore = defineStore('pocket', () => {
  const pocket = ref(null);
  const isLoading = ref(false);
  const error = ref(null);
  const subscribedDivisions = new Set();
  let userChannelId = null;
  let refetchTimer = null;
  let listenGeneration = 0;

  const classes = computed(() => pocket.value?.classes ?? []);
  const invites = computed(() => pocket.value?.invites ?? []);
  const viewer = computed(() => pocket.value?.viewer ?? null);

  const publicQuestions = computed(() => {
    if (viewer.value !== 'student') {
      return [];
    }

    const seen = new Set();
    const questions = [];

    for (const division of classes.value) {
      for (const question of division.open_questions ?? []) {
        if (seen.has(question.id)) {
          continue;
        }
        seen.add(question.id);
        questions.push({
          ...question,
          division_name: division.name,
        });
      }
    }

    return questions;
  });

  async function fetchPocket({ quiet = false } = {}) {
    const generation = listenGeneration;
    if (!quiet) {
      isLoading.value = true;
    }
    error.value = null;

    try {
      const response = await axios.get('/api/my/pocket');
      if (generation !== listenGeneration) {
        return response.data;
      }
      pocket.value = response.data;
      syncDivisionChannels(classes.value.map(division => division.id));
      return response.data;
    } catch (err) {
      if (generation === listenGeneration) {
        error.value = err.response?.data?.message || 'Impossible de charger la poche.';
      }
      throw err;
    } finally {
      if (!quiet && generation === listenGeneration) {
        isLoading.value = false;
      }
    }
  }

  function scheduleRefetch() {
    clearTimeout(refetchTimer);
    refetchTimer = setTimeout(() => {
      fetchPocket({ quiet: true }).catch(() => {});
    }, 300);
  }

  function listen(channel) {
    channel
      .listen('.SessionOpenedForDivision', scheduleRefetch)
      .listen('.SessionClosedForDivision', scheduleRefetch)
      .listen('.JumpActivated', scheduleRefetch)
      .listen('.JumpReopened', scheduleRefetch)
      .listen('.PocketChanged', scheduleRefetch);
  }

  function syncDivisionChannels(classIds) {
    if (!window.Echo) {
      return;
    }

    const next = new Set(classIds);

    for (const id of subscribedDivisions) {
      if (!next.has(id)) {
        window.Echo.leave(`division.${id}`);
        subscribedDivisions.delete(id);
      }
    }

    for (const id of next) {
      if (subscribedDivisions.has(id)) {
        continue;
      }
      listen(window.Echo.private(`division.${id}`));
      subscribedDivisions.add(id);
    }
  }

  function subscribeUser(userId) {
    if (!window.Echo || !userId || userChannelId === userId) {
      return;
    }

    if (userChannelId) {
      window.Echo.leave(`App.Models.User.${userChannelId}`);
    }

    userChannelId = userId;
    window.Echo.private(`App.Models.User.${userId}`).listen('.PocketChanged', scheduleRefetch);
  }

  function leaveAll() {
    listenGeneration += 1;
    clearTimeout(refetchTimer);
    refetchTimer = null;

    if (!window.Echo) {
      subscribedDivisions.clear();
      userChannelId = null;
      return;
    }

    for (const id of subscribedDivisions) {
      window.Echo.leave(`division.${id}`);
    }
    subscribedDivisions.clear();

    if (userChannelId) {
      window.Echo.leave(`App.Models.User.${userChannelId}`);
      userChannelId = null;
    }
  }

  return {
    pocket,
    isLoading,
    error,
    classes,
    invites,
    viewer,
    publicQuestions,
    fetchPocket,
    subscribeUser,
    leaveAll,
  };
});

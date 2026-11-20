(function () {
  "use strict";
  const search = document.querySelector("#course-search");
  const mode = document.querySelector("#course-mode");
  const grid = document.querySelector("#course-grid");
  const count = document.querySelector("#course-count");
  const empty = document.querySelector("#course-empty");
  const clear = document.querySelector("#course-clear");
  if (!search || !mode || !grid || !count) return;
  const cards = Array.from(grid.querySelectorAll(".ssp-course-result"));
  function filterCourses() {
    const term = search.value.trim().toLocaleLowerCase();
    const selectedMode = mode.value;
    let visible = 0;
    cards.forEach(function (card) {
      const matchesText = !term || (card.dataset.courseSearch || "").includes(term);
      const modes = (card.dataset.courseModes || "").split(",");
      const matchesMode = !selectedMode || modes.includes(selectedMode);
      const show = matchesText && matchesMode;
      card.hidden = !show;
      if (show) visible++;
    });
    count.textContent = visible + (visible === 1 ? " course" : " courses");
    if (empty) empty.hidden = visible !== 0;
  }
  search.addEventListener("input", filterCourses);
  mode.addEventListener("change", filterCourses);
  if (clear) clear.addEventListener("click", function () {
    search.value = "";
    mode.value = "";
    filterCourses();
    search.focus();
  });
  filterCourses();
})();
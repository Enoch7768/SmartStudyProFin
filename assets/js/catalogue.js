(function () {
  "use strict";
  const courseSearch = document.querySelector("#course-search");
  const courseMode = document.querySelector("#course-mode");
  const courseGrid = document.querySelector("#course-grid");
  const courseCount = document.querySelector("#course-count");
  const courseEmpty = document.querySelector("#course-empty");
  const courseClear = document.querySelector("#course-clear");
  if (courseSearch && courseMode && courseGrid && courseCount) {
    const cards = Array.from(courseGrid.querySelectorAll(".ssp-course-result"));
    function filterCourses() {
      const term = courseSearch.value.trim().toLocaleLowerCase();
      const selectedMode = courseMode.value;
      let visible = 0;
      cards.forEach(function (card) {
        const matchesText = !term || (card.dataset.courseSearch || "").includes(term);
        const matchesMode = !selectedMode || (card.dataset.courseModes || "").split(",").includes(selectedMode);
        card.hidden = !(matchesText && matchesMode);
        if (!card.hidden) visible++;
      });
      courseCount.textContent = visible + (visible === 1 ? " course" : " courses");
      if (courseEmpty) courseEmpty.hidden = visible !== 0;
    }
    courseSearch.addEventListener("input", filterCourses);
    courseMode.addEventListener("change", filterCourses);
    if (courseClear) courseClear.addEventListener("click", function () {
      courseSearch.value = "";
      courseMode.value = "";
      filterCourses();
      courseSearch.focus();
    });
    filterCourses();
  }

  const productSearch = document.querySelector("#product-search");
  const productType = document.querySelector("#product-type");
  const productCategory = document.querySelector("#product-category");
  const productGrid = document.querySelector("#product-grid");
  const productCount = document.querySelector("#product-count");
  const productEmpty = document.querySelector("#product-empty");
  const productClear = document.querySelector("#product-clear");
  if (productSearch && productType && productCategory && productGrid && productCount) {
    const products = Array.from(productGrid.querySelectorAll(".ssp-product-result"));
    function filterProducts() {
      const term = productSearch.value.trim().toLocaleLowerCase();
      const type = productType.value;
      const category = productCategory.value;
      let visible = 0;
      products.forEach(function (product) {
        const matchesText = !term || (product.dataset.productSearch || "").includes(term);
        const matchesType = !type || product.dataset.productType === type;
        const matchesCategory = !category || product.dataset.productCategory === category;
        product.hidden = !(matchesText && matchesType && matchesCategory);
        if (!product.hidden) visible++;
      });
      productCount.textContent = visible + (visible === 1 ? " item" : " items");
      if (productEmpty) productEmpty.hidden = visible !== 0;
    }
    productSearch.addEventListener("input", filterProducts);
    productType.addEventListener("change", filterProducts);
    productCategory.addEventListener("change", filterProducts);
    if (productClear) productClear.addEventListener("click", function () {
      productSearch.value = "";
      productType.value = "";
      productCategory.value = "";
      filterProducts();
      productSearch.focus();
    });
    filterProducts();
  }
})();
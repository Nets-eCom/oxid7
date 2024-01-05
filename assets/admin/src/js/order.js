const ready = (callback) => {
    if (document.readyState == "loading") {
        document.addEventListener("DOMContentLoaded", callback);
    } else {
        callback();
    }
};

ready(() => {
    document.querySelectorAll("tr.lining").forEach((row) => {
        const quan = row.querySelector(".quant").value;
        const qty = row.querySelector(".single.qty").value;
        const tep = row.querySelector(".single.qty").getAttribute("step");
        const plus = row.querySelector(".plus");
        const minus = row.querySelector(".minus");

        if (quan == tep) {
            minus.classList.add('is-disabled');
            plus.classList.add('is-disabled');
            row.querySelector('.single.qty.value').classList.add('is-disabled');
        }

        if (quan == qty) {
            plus.classList.add('is-disabled');
        }
    });

    document.querySelectorAll(".single.qty").forEach((element) => {
        element.addEventListener('input', function (e) {
            const currentSingle = parseFloat(this.value);
            const ref = this.closest('.quantity').querySelector(".reference").value;
            const price = this.closest('.quantity').querySelector(".price").value;
            const currency = this.closest('.quantity').querySelector(".currency").value;
            const plus = this.closest('.quantity').querySelector(".plus");
            const minus = this.closest('.quantity').querySelector(".minus");
            const min = parseFloat(this.getAttribute('min'));
            const max = parseFloat(this.getAttribute('max'));
            document.querySelector('#price_' + ref).textContent = parseFloat(currentSingle * price).toFixed(2) + ' ' + currency;

            if (!currentSingle || currentSingle == "" || isNaN(currentSingle)) {
                this.value = min;
                document.querySelector('#price_' + ref).textContent = parseFloat(min * price).toFixed(2) + ' ' + currency;
                minus.classList.add('is-disabled');
            }

            if (min > currentSingle && e.keyCode !== 46 && e.keyCode !== 8) {
                e.preventDefault();
                this.value = min;
                document.querySelector('#price_' + ref).textContent = parseFloat(min * price).toFixed(2) + ' ' + currency;
                minus.classList.add('is-disabled');
            }

            if (currentSingle > max && e.keyCode !== 46 && e.keyCode !== 8) {
                e.preventDefault();
                this.value = max;
                document.querySelector('#price_' + ref).textContent = parseFloat(max * price).toFixed(2) + ' ' + currency;
                plus.classList.add('is-disabled');
            }

            if (currentSingle > min && currentSingle < max) {
                e.preventDefault();
                minus.classList.remove('is-disabled');
                plus.classList.remove('is-disabled');
            }
        });
    });

    document.querySelectorAll(".quantity").forEach((quantity) => {
        quantity.addEventListener('click', function (event) {
            const ref = this.querySelector(".reference").value;
            const price = this.querySelector(".price").value;
            const currency = this.querySelector(".currency").value;
            const qty = this.querySelector(".qty");
            let currentInput = parseFloat(qty.value);
            const min = parseFloat(qty.getAttribute('min')) || 1;
            const max = parseFloat(qty.getAttribute('max')) || '';
            const minus = this.querySelector(".minus");
            const plus = this.querySelector(".plus");

            if (qty.value == 1 && event.target.classList.contains('minus')) {
                minus.classList.add('is-disabled');
                return;
            }

            if (!currentInput || isNaN(currentInput)) {
                currentInput = 1;
            }

            if (event.target.classList.contains('plus')) {
                if (max && currentInput >= max) {
                    qty.value = max;
                    document.querySelector('#item_' + ref).value = max;
                    document.querySelector('#price_' + ref).textContent = parseFloat(max * price).toFixed(2) + ' ' + currency;
                    plus.classList.add('is-disabled');
                } else {
                    currentInput++;
                    qty.value = currentInput;
                    document.querySelector('#item_' + ref).value = currentInput;
                    document.querySelector('#price_' + ref).textContent = parseFloat(currentInput * price).toFixed(2) + ' ' + currency;
                    minus.classList.remove('is-disabled');
                    plus.classList.remove('is-disabled');
                }
            } else {
                if (min && (min === currentInput || currentInput < min)) {
                    qty.value = min;
                    document.querySelector('#item_' + ref).value = min;
                    document.querySelector('#price_' + ref).textContent = parseFloat(min * price).toFixed(2) + ' ' + currency;
                    minus.classList.add('is-disabled');
                } else if (currentInput > 0) {
                    currentInput--;
                    qty.value = currentInput;
                    document.querySelector('#item_' + ref).value = currentInput;
                    document.querySelector('#price_' + ref).textContent = parseFloat(currentInput * price).toFixed(2) + ' ' + currency;
                    minus.classList.remove('is-disabled');
                    plus.classList.remove('is-disabled');
                }
            }
        });
    });
});
